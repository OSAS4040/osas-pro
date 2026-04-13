<?php

declare(strict_types=1);

namespace App\Intelligence\DTO;

/**
 * Unified attention item (rule-based, no AI).
 */
final readonly class AttentionItemDto
{
    public function __construct(
        public string $type,
        public string $severity,
        public string $messageKey,
        public string $relatedEntity,
        public string $createdAt,
    ) {}

    /**
     * @return array{type: string, severity: string, message_key: string, related_entity: string, created_at: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'message_key' => $this->messageKey,
            'related_entity' => $this->relatedEntity,
            'created_at' => $this->createdAt,
        ];
    }
}
