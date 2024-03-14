<?php
declare(strict_types=1);
namespace Zodream\Route\Rewrite;

use Zodream\Http\Uri;
use Zodream\Infrastructure\Contracts\Application;
use Zodream\Infrastructure\Contracts\Translator;
use Zodream\Infrastructure\I18n\I18n;

class LocaleURLEncoder implements URLEncoder {

    const LocaleDisabledKey = 'locale_disabled';

    public function __construct(
        protected Application $app,
        protected Translator $translator) {
    }

    public function decode(Uri $url, callable $next): Uri {
        $url = $next($url);
        return $url->setPath($this->decodeLocale($url->getPath()));
    }

    public function encode(Uri $url, callable $next): Uri {
        if ($this->app->has(static::LocaleDisabledKey)) {
            return $next($url);
        }
        $url->setPath($this->encodeLocale($url->getPath()));
        return $next($url);
    }

    protected function decodeLocale(string $path): string {
        if (empty($path)) {
            return $path;
        }
        $i = strpos($path, '/');
        $locale = $i === false ? $path : substr($path, 0, $i);
        if (!$this->translator->isLocale($locale)) {
            return $path;
        }
        $this->app->setLocale($locale);
        return $i === false ?  '' : substr($path, $i + 1);
    }

    protected function encodeLocale(string $path): string {
        $locale = $this->app->getLocale();
        if (empty($locale) || $locale === I18n::DEFAULT_LANGUAGE) {
            return $path;
        }
        return $path === '' ? $locale : sprintf('%s/%s', $locale, trim($path, '/'));
    }
}