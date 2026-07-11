<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Domain\Audit;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;

final readonly class AuditEvent
{
    /**
     * @param array<string, bool|int|string|null> $metadata
     */
    public function __construct(
        private string $eventCode,
        private string $severity,
        private SettingScope $scope,
        private AuditActor $actor,
        private string $subjectType,
        private string $subjectId,
        private string $correlationId,
        private array $metadata,
        private DateTimeImmutable $occurredAt,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,95}$/D', $eventCode) !== 1) {
            throw new InvalidArgumentException('Audit event code is invalid.');
        }

        if (!in_array($severity, ['info', 'warning', 'error'], true)) {
            throw new InvalidArgumentException('Audit severity is invalid.');
        }

        if (preg_match('/^[a-z][a-z0-9_.-]{1,63}$/D', $subjectType) !== 1) {
            throw new InvalidArgumentException('Audit subject type is invalid.');
        }

        if ($subjectId === '' || strlen($subjectId) > 128) {
            throw new InvalidArgumentException('Audit subject ID is invalid.');
        }

        if ($correlationId === '' || strlen($correlationId) > 64) {
            throw new InvalidArgumentException('Audit correlation ID is invalid.');
        }

        $this->metadataJson();
    }

    public function eventCode(): string
    {
        return $this->eventCode;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    public function scope(): SettingScope
    {
        return $this->scope;
    }

    public function actor(): AuditActor
    {
        return $this->actor;
    }

    public function subjectType(): string
    {
        return $this->subjectType;
    }

    public function subjectId(): string
    {
        return $this->subjectId;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    /**
     * @return array<string, bool|int|string|null>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function metadataJson(): string
    {
        $metadata = $this->metadata;
        ksort($metadata);

        try {
            return json_encode(
                $metadata,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Audit metadata cannot be serialized.', previous: $exception);
        }
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
