<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;
use Spatie\DataTransferObject\DataTransferObject;

class LicenseSignatureData extends DataTransferObject
{
    public string $key;
    public string $userId;
    public string $productId;
    public string $type;
    public int $seats;
    public Carbon $validFrom;
    public ?Carbon $validUntil;
    public array $features;
    public ?array $restrictions;
    public ?string $encryptionKeyId;

    public static function fromArray(array $data): self
    {
        return new self([
            'key' => $data['key'],
            'userId' => $data['user_id'],
            'productId' => $data['product_id'],
            'type' => $data['type'],
            'seats' => $data['seats'],
            'validFrom' => Carbon::parse($data['valid_from']),
            'validUntil' => isset($data['valid_until']) ? Carbon::parse($data['valid_until']) : null,
            'features' => $data['features'] ?? [],
            'restrictions' => $data['restrictions'] ?? null,
            'encryptionKeyId' => $data['encryption_key_id'] ?? null,
        ]);
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'user_id' => $this->userId,
            'product_id' => $this->productId,
            'type' => $this->type,
            'seats' => $this->seats,
            'valid_from' => $this->validFrom->toIso8601String(),
            'valid_until' => $this->validUntil?->toIso8601String(),
            'features' => $this->features,
            'restrictions' => $this->restrictions,
            'encryption_key_id' => $this->encryptionKeyId,
        ];
    }
}