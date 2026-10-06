<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/** Turns user-submitted rich text (Trix output) into safe HTML. The only way HTML reaches the database. */
class HtmlSanitizer
{
    private ?HTMLPurifier $purifier = null;

    public function clean(?string $html): string
    {
        return trim($this->purifier()->purify((string) $html));
    }

    /** True when the body has no visible text after cleaning (e.g. Trix's empty "<div><br></div>"). */
    public function isBlank(?string $html): bool
    {
        $text = html_entity_decode(strip_tags($this->clean($html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{A0}", ' ', $text)) === '';
    }

    private function purifier(): HTMLPurifier
    {
        if ($this->purifier) {
            return $this->purifier;
        }

        $cachePath = storage_path('framework/cache/htmlpurifier');

        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.SerializerPath', $cachePath);
        $config->set('HTML.Allowed', 'p,div,br,strong,b,em,i,u,s,del,a[href],ul,ol,li,h1,h2,h3,blockquote,pre,code');
        $config->set('HTML.Nofollow', true);
        $config->set('HTML.TargetBlank', true);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('AutoFormat.RemoveEmpty.RemoveNbsp', true);

        return $this->purifier = new HTMLPurifier($config);
    }
}
