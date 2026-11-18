<?php

namespace App\Modules\Search\OpenSearch;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client REST minimal pour OpenSearch (compatible Elasticsearch).
 * Volontairement sans dépendance : seules les quelques routes utilisées par l'application.
 */
class OpenSearchClient
{
    public function __construct(
        private readonly string $host,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private readonly float $timeout = 5,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            rtrim(config('search.host'), '/'),
            config('search.username'),
            config('search.password'),
            config('search.timeout'),
        );
    }

    public function ping(): bool
    {
        try {
            return $this->http()->timeout(2)->get('/')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function indexExists(string $index): bool
    {
        return $this->http()->head('/'.$index)->successful();
    }

    public function createIndex(string $index, array $definition): void
    {
        $this->send('put', '/'.$index, $definition);
    }

    public function deleteIndex(string $index): void
    {
        $response = $this->http()->delete('/'.$index);

        if ($response->failed() && $response->status() !== 404) {
            $response->throw();
        }
    }

    /** @return list<string> index pointés par l'alias */
    public function aliasTargets(string $alias): array
    {
        $response = $this->http()->get('/_alias/'.$alias);

        return $response->status() === 404 ? [] : array_keys($response->throw()->json());
    }

    /** Bascule atomique de l'alias vers un nouvel index (réindexation sans interruption). */
    public function swapAlias(string $alias, string $newIndex): void
    {
        $actions = array_map(fn (string $old) => ['remove' => ['index' => $old, 'alias' => $alias]], $this->aliasTargets($alias));
        $actions[] = ['add' => ['index' => $newIndex, 'alias' => $alias]];

        $this->send('post', '/_aliases', ['actions' => $actions]);
    }

    public function index(string $index, string $id, array $document, bool $refresh = false): void
    {
        $this->send('put', "/{$index}/_doc/".rawurlencode($id).($refresh ? '?refresh=true' : ''), $document);
    }

    public function delete(string $index, string $id, bool $refresh = false): void
    {
        $response = $this->http()->delete("/{$index}/_doc/".rawurlencode($id).($refresh ? '?refresh=true' : ''));

        if ($response->failed() && $response->status() !== 404) {
            $response->throw();
        }
    }

    /**
     * Indexation par lots (NDJSON).
     *
     * @param  array<string, array>  $documents  id => document
     */
    public function bulkIndex(string $index, array $documents): void
    {
        if ($documents === []) {
            return;
        }

        $lines = '';
        foreach ($documents as $id => $document) {
            $lines .= json_encode(['index' => ['_index' => $index, '_id' => (string) $id]])."\n";
            $lines .= json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
        }

        $response = $this->http()
            ->withBody($lines, 'application/x-ndjson')
            ->post('/_bulk')
            ->throw();

        if ($response->json('errors')) {
            $first = collect($response->json('items'))->first(fn ($item) => isset($item['index']['error']));
            throw new SearchException('Indexation par lots en échec : '.json_encode($first['index']['error'] ?? null));
        }
    }

    public function refresh(string $index): void
    {
        $this->send('post', "/{$index}/_refresh");
    }

    public function search(string $index, array $body): array
    {
        return $this->send('post', "/{$index}/_search", $body)->json();
    }

    private function send(string $method, string $path, ?array $body = null): Response
    {
        try {
            return $this->http()->{$method}($path, $body ?? [])->throw();
        } catch (RequestException $e) {
            $reason = $e->response->json('error.reason') ?? $e->response->json('error.root_cause.0.reason') ?? $e->getMessage();
            throw new SearchException("OpenSearch {$method} {$path} : {$reason}", previous: $e);
        } catch (ConnectionException $e) {
            // Moteur injoignable : erreur métier explicite (503 côté API), jamais une 500 brute.
            throw new SearchException("OpenSearch injoignable ({$this->host}) : {$e->getMessage()}", previous: $e);
        }
    }

    private function http(): PendingRequest
    {
        $request = Http::baseUrl($this->host)->acceptJson()->asJson()->timeout($this->timeout);

        return $this->username ? $request->withBasicAuth($this->username, (string) $this->password) : $request;
    }
}
