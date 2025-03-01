<?php

/**
 * @package Tagged
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Tagged\Html;

use DecodeLabs\Exceptional;
use DecodeLabs\Glitch\Proxy as Glitch;
use DecodeLabs\Tagged\Buffer;
use DecodeLabs\Tagged\Builder\Tag as TagInterface;
use DecodeLabs\Tagged\Markup;
use DecodeLabs\Veneer\Plugin\AccessTarget as VeneerPluginAccessTarget;
use DecodeLabs\Veneer\Plugin\AccessTargetTrait as VeneerPluginAccessTargetTrait;
use DecodeLabs\Veneer\Plugin as VeneerPlugin;
use DecodeLabs\Veneer\Plugin\Provider as VeneerPluginProvider;
use DecodeLabs\Veneer\Plugin\ProviderTrait as VeneerPluginProviderTrait;

class Factory implements Markup, VeneerPluginProvider, VeneerPluginAccessTarget
{
    use VeneerPluginProviderTrait;
    use VeneerPluginAccessTargetTrait;

    public const PLUGINS = [
        'parse',
        'toText',
        'icon',
        'number',
        'time',
        'embed'
    ];


    /**
     * Instance shortcut to el
     */
    public function __invoke(string $name, $content, array $attributes = null): Element
    {
        return $this->el($name, $content, $attributes);
    }

    /**
     * Call named widget from instance
     */
    public function __call(string $name, array $args): Element
    {
        return Element::create($name, ...$args);
    }

    /**
     * Dummy string generator to satisfy Markup dep
     */
    public function __toString(): string
    {
        return '';
    }



    /**
     * Stub to get empty plugin list to avoid broken targets
     */
    public function getVeneerPluginNames(): array
    {
        return static::PLUGINS;
    }

    /**
     * Load factory plugins
     */
    public function loadVeneerPlugin(string $name): VeneerPlugin
    {
        if (!in_array($name, self::PLUGINS)) {
            throw Exceptional::InvalidArgument($name . ' is not a recognised Veneer plugin');
        }

        $class = '\\DecodeLabs\\Tagged\\Html\\Plugins\\' . ucfirst($name);
        return new $class($this);
    }






    /**
     * Create a standalone tag
     */
    public function tag(string $name, array $attributes = null): TagInterface
    {
        return new Tag($name, $attributes);
    }

    /**
     * Create a standalone element
     */
    public function el(string $name, $content = null, array $attributes = null): Element
    {
        return Element::create($name, $content, $attributes);
    }

    /**
     * Wrap raw html string
     */
    public function raw($html): Buffer
    {
        return new Buffer((string)$html);
    }

    /**
     * Normalize arbitrary content
     */
    public function wrap(...$content): Markup
    {
        return ContentCollection::normalize($content);
    }

    /**
     * Wrap arbitrary content as collection
     */
    public function content(...$content): Markup
    {
        return new ContentCollection($content);
    }




    /**
     * Generate nested list
     */
    public function list(?iterable $list, string $container, ?string $name, callable $callback = null, array $attributes = []): Element
    {
        $output = Element::create($container, function () use ($list, $name, $callback) {
            if (!$list) {
                return;
            }

            $i = 0;

            foreach ($list as $key => $item) {
                $i++;

                if ($name === null) {
                    // Unwrapped
                    if ($callback) {
                        yield $callback($item, null, $key, $i);
                    } else {
                        yield $item;
                    }
                } else {
                    // Wrapped
                    yield Element::create($name, function ($el) use ($key, $item, $callback, $i) {
                        if ($callback) {
                            return $callback($item, $el, $key, $i);
                        } else {
                            return $item;
                        }
                    });
                }
            }
        }, $attributes);

        $output->setRenderEmpty(false);
        return $output;
    }


    /**
     * Generate naked list
     */
    public function elements(?iterable $list, ?string $name, callable $callback = null, array $attributes = []): Buffer
    {
        return ContentCollection::normalize(function () use ($list, $name, $callback, $attributes) {
            if (!$list) {
                return;
            }

            $i = 0;

            foreach ($list as $key => $item) {
                $i++;

                if ($name === null) {
                    // Unwrapped
                    if ($callback) {
                        yield $callback($item, null, $key, $i);
                    } else {
                        yield $item;
                    }
                } else {
                    // Wrapped
                    yield Element::create($name, function ($el) use ($key, $item, $callback, $i) {
                        if ($callback) {
                            return $callback($item, $el, $key, $i);
                        } else {
                            return $item;
                        }
                    }, $attributes);
                }
            }
        });
    }


    /**
     * Generate unwrapped naked list
     */
    public function loop(?iterable $list, callable $callback = null): Buffer
    {
        return ContentCollection::normalize(function () use ($list, $callback) {
            if (!$list) {
                return;
            }

            $i = 0;

            foreach ($list as $key => $item) {
                $i++;

                if ($callback) {
                    yield $callback($item, null, $key, $i);
                } else {
                    yield $item;
                }
            }
        });
    }


    /**
     * Create a standard ul > li structure
     */
    public function uList(?iterable $list, callable $renderer = null, array $attributes = []): Element
    {
        return $this->list($list, 'ul', '?li', $renderer ?? function ($value) {
            return $value;
        }, $attributes);
    }

    /**
     * Create a standard ol > li structure
     */
    public function oList(?iterable $list, callable $renderer = null, array $attributes = []): Element
    {
        return $this->list($list, 'ol', '?li', $renderer ?? function ($value) {
            return $value;
        }, $attributes);
    }

    /**
     * Create a standard dl > dt + dd structure
     */
    public function dList(?iterable $list, callable $renderer = null, array $attributes = []): Element
    {
        $renderer = $renderer ?? function ($value) {
            return $value;
        };

        $output = Element::create('dl', function () use ($list, $renderer) {
            if (!$list) {
                return;
            }

            foreach ($list as $key => $item) {
                $dt = Element::create('dt', null);

                // Render dd tag before dt so that renderer can add contents to dt first
                $dd = (string)Element::create('dd', function ($dd) use ($key, $item, $renderer, &$i, $dt) {
                    return $renderer($item, $dt, $dd, $key, ++$i);
                });

                if ($dt->isEmpty()) {
                    $dt->append($key);
                }

                yield $dt;
                yield new Buffer($dd);
            }
        }, $attributes);

        $output->setRenderEmpty(false);
        return $output;
    }

    /**
     * Create an inline comma separated list with optional item limit
     */
    public function iList(?iterable $list, callable $renderer = null, string $delimiter = null, string $finalDelimiter = null, int $limit = null): Element
    {
        if ($delimiter === null) {
            $delimiter = ', ';
        }

        return Element::create('span.list', function ($el) use ($list, $renderer, $delimiter, $finalDelimiter, $limit) {
            $el->setRenderEmpty(false);

            if (!$list) {
                return;
            }

            $first = true;
            $i = $more = 0;

            if ($finalDelimiter === null) {
                $finalDelimiter = $delimiter;
            }

            $items = [];

            foreach ($list as $key => $item) {
                if ($item === null) {
                    continue;
                }

                $i++;

                $cellTag = Element::create('?span', function ($el) use ($key, $item, $renderer, &$i) {
                    if ($renderer) {
                        return $renderer($item, $el, $key, $i);
                    } else {
                        return $item;
                    }
                });

                if (empty($tagString = (string)$cellTag)) {
                    $i--;
                    continue;
                }

                if ($limit !== null && $i > $limit) {
                    $more++;
                    continue;
                }


                $items[] = new Buffer($tagString);
            }

            $total = count($items);

            foreach ($items as $i => $item) {
                if (!$first) {
                    if ($i + 1 == $total) {
                        yield $finalDelimiter;
                    } else {
                        yield $delimiter;
                    }
                }

                yield $item;

                $first = false;
            }

            if ($more) {
                yield Element::create('em.more', '… +' . $more);
            }
        });
    }




    /**
     * Create image tag
     */
    public function image($url, string $alt = null, $width = null, $height = null): Element
    {
        $output = $this->el('img', null, [
            'src' => $url,
            'alt' => $alt
        ]);

        if ($width !== null) {
            $output->setAttribute('width', $width);
        }

        if ($height !== null) {
            $output->setAttribute('height', $height);
        }

        return $output;
    }



    /**
     * Escape HTML
     */
    public function esc($value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        } catch (\Throwable $e) {
            Glitch::logException($e);
            return (string)$value;
        }
    }
}
