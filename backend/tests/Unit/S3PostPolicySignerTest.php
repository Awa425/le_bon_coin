<?php

namespace Tests\Unit;

use App\Modules\Media\Uploads\S3PostPolicySigner;
use Tests\TestCase;

/** La politique signée doit imposer au stockage la clé, le type et la taille exacts du fichier. */
class S3PostPolicySignerTest extends TestCase
{
    public function test_the_signed_policy_pins_key_type_and_exact_size(): void
    {
        config(['filesystems.disks.signer-test' => [
            'driver' => 's3',
            'key' => 'cle',
            'secret' => 'secret',
            'region' => 'us-east-1',
            'bucket' => 'marketplace-uploads',
            'endpoint' => 'http://localhost:8333',
            'use_path_style_endpoint' => true,
        ]]);

        $form = (new S3PostPolicySigner('signer-test'))->sign('originals/l1/p1.jpg', 'image/jpeg', 123456, now()->addMinutes(15));

        $this->assertSame('http://localhost:8333/marketplace-uploads', $form->url);
        $this->assertSame('originals/l1/p1.jpg', $form->fields['key']);
        $this->assertSame('image/jpeg', $form->fields['Content-Type']);
        $this->assertSame('AWS4-HMAC-SHA256', $form->fields['X-Amz-Algorithm']);
        $this->assertNotEmpty($form->fields['X-Amz-Signature']);

        $policy = json_decode(base64_decode($form->fields['Policy']), true);
        $this->assertContains(['eq', '$key', 'originals/l1/p1.jpg'], $policy['conditions']);
        $this->assertContains(['eq', '$Content-Type', 'image/jpeg'], $policy['conditions']);
        $this->assertContains(['content-length-range', 123456, 123456], $policy['conditions']);
        $this->assertContains(['bucket' => 'marketplace-uploads'], $policy['conditions']);
        $this->assertEqualsWithDelta(now()->addMinutes(15)->timestamp, strtotime($policy['expiration']), 5);
        $this->assertSame('file', $form->toArray()['file_field']);
    }
}
