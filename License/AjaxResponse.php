<?php

declare(strict_types=1);

namespace JTL\License;

use JsonSerializable;

/**
 * Class AjaxResponse
 * @package JTL\License
 */
class AjaxResponse implements JsonSerializable
{
    public string $html = '';

    public string $notification = '';

    public string $id = '';

    public string $status = 'OK';

    public ?string $redirect = null;

    public string $action = '';

    public string $error = '';

    public mixed $additional = null;

    /**
     * @var array<string, string>
     */
    public array $replaceWith = [];

    /**
     * @return array<string, string|null|array<string, string>>
     */
    public function jsonSerialize(): array
    {
        return [
            'error'        => $this->error,
            'status'       => $this->status,
            'action'       => $this->action,
            'id'           => $this->id,
            'notification' => \trim($this->notification),
            'html'         => \trim($this->html),
            'replaceWith'  => $this->replaceWith,
            'redirect'     => $this->redirect
        ];
    }
}
