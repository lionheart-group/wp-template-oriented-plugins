<?php

namespace TofuPlugin\Validation\Rules;

/**
 * Allowed file extensions (`extension:txt,pdf`).
 *
 * Same check as `mimes`, different message wording — kept as a distinct
 * class only so each keeps its own translated text.
 */
class ExtensionRule extends MimesRule
{
    protected string $message = 'rule.extension';

    public function fillParameters(array $params): self
    {
        parent::fillParameters($params);
        $this->params['allowed_extensions'] = $this->params['allowed_types'];

        return $this;
    }
}
