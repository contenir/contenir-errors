<?php

declare(strict_types=1);

namespace Contenir\Errors;

/**
 * Immutable admin-authored content for a single HTTP error status.
 *
 * The body is expected to be a sanitized HTML fragment (inline elements
 * only — no scripts, styles, or block-level layout). Sanitization is the
 * writer's responsibility (typically the admin save flow); readers render
 * the body raw and trust the contract.
 *
 * @api
 */
final readonly class ErrorPage
{
    public function __construct(
        public int $status,
        public string $title,
        public string $body,
    ) {}

    /**
     * Treats a page with no title and no body as effectively absent. Listeners
     * use this to decide whether to swap the framework's default rendering.
     */
    public function isEmpty(): bool
    {
        return '' === $this->title && '' === $this->body;
    }
}
