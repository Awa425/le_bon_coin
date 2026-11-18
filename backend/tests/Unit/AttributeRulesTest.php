<?php

namespace Tests\Unit;

use App\Enums\AttributeType;
use App\Models\AttributeDefinition;
use App\Modules\Catalog\Validation\AttributeRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AttributeRulesTest extends TestCase
{
    private AttributeRules $rules;

    /** @var Collection<string, AttributeDefinition> */
    private Collection $schema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules = new AttributeRules;
        $this->schema = collect([
            'kilometrage' => new AttributeDefinition(['key' => 'kilometrage', 'label' => 'Kilométrage', 'type' => AttributeType::Number, 'min_value' => 0, 'max_value' => 2000000, 'is_required' => true]),
            'carburant' => new AttributeDefinition(['key' => 'carburant', 'label' => 'Carburant', 'type' => AttributeType::Select, 'options' => ['essence', 'diesel'], 'is_required' => true]),
            'options' => new AttributeDefinition(['key' => 'options', 'label' => 'Options', 'type' => AttributeType::MultiSelect, 'options' => ['gps', 'clim', 'toit_ouvrant']]),
            'premiere_main' => new AttributeDefinition(['key' => 'premiere_main', 'label' => 'Première main', 'type' => AttributeType::Boolean]),
            'couleur' => new AttributeDefinition(['key' => 'couleur', 'label' => 'Couleur', 'type' => AttributeType::Text]),
        ]);
    }

    /** @return array<string, list<string>> */
    private function errors(array $values, bool $enforceRequired): array
    {
        $validator = Validator::make(
            ['attribute_values' => $values],
            $this->rules->rules($this->schema, $enforceRequired),
        );

        return $validator->errors()->toArray();
    }

    public function test_valid_values_pass_in_both_modes(): void
    {
        $values = ['kilometrage' => '120000', 'carburant' => 'diesel', 'options' => ['gps', 'clim'], 'premiere_main' => '1', 'couleur' => 'Bleu'];

        $this->assertSame([], $this->errors($values, enforceRequired: false));
        $this->assertSame([], $this->errors($values, enforceRequired: true));
    }

    public function test_draft_mode_accepts_missing_required_attributes(): void
    {
        $this->assertSame([], $this->errors(['couleur' => 'Rouge'], enforceRequired: false));
        $this->assertSame([], $this->errors([], enforceRequired: false));
    }

    public function test_publish_mode_requires_mandatory_attributes(): void
    {
        $errors = $this->errors(['couleur' => 'Rouge'], enforceRequired: true);

        $this->assertArrayHasKey('attribute_values.kilometrage', $errors);
        $this->assertArrayHasKey('attribute_values.carburant', $errors);
        $this->assertArrayNotHasKey('attribute_values.options', $errors);
    }

    public function test_unknown_keys_are_rejected(): void
    {
        $this->assertArrayHasKey('attribute_values', $this->errors(['cylindree' => 125], enforceRequired: false));
    }

    public function test_types_bounds_and_options_are_enforced(): void
    {
        $errors = $this->errors([
            'kilometrage' => -5,
            'carburant' => 'charbon',
            'options' => ['gps', 'jacuzzi'],
            'premiere_main' => 'peut-etre',
        ], enforceRequired: false);

        $this->assertArrayHasKey('attribute_values.kilometrage', $errors);
        $this->assertArrayHasKey('attribute_values.carburant', $errors);
        $this->assertArrayHasKey('attribute_values.options.1', $errors);
        $this->assertArrayHasKey('attribute_values.premiere_main', $errors);
    }

    public function test_multi_select_rejects_duplicates(): void
    {
        $errors = $this->errors(['options' => ['gps', 'gps']], enforceRequired: false);

        $this->assertArrayHasKey('attribute_values.options.0', $errors);
    }

    public function test_human_labels_are_used_in_messages(): void
    {
        $validator = Validator::make(
            ['attribute_values' => ['carburant' => 'charbon']],
            $this->rules->rules($this->schema, false),
            [],
            $this->rules->attributeNames($this->schema),
        );

        $this->assertStringContainsString('Carburant', $validator->errors()->first('attribute_values.carburant'));
    }

    public function test_normalize_casts_values_and_drops_empty_ones(): void
    {
        $normalized = $this->rules->normalize($this->schema, [
            'kilometrage' => '120000',
            'premiere_main' => '0',
            'options' => ['clim'],
            'couleur' => '',
            'carburant' => null,
        ]);

        $this->assertSame(['kilometrage' => 120000, 'premiere_main' => false, 'options' => ['clim']], $normalized);
    }
}
