<?php

namespace Optimus\FineuploaderServer\Middleware;

use Closure;
use Intervention\Image\ImageManager;
use Optimus\FineuploaderServer\File\Edition;
use Optimus\FineuploaderServer\Middleware\Naming\ThumbnailSuffixStrategy;
use Optimus\Onion\LayerInterface;

class ThumbnailCreator implements LayerInterface {

    const editionKey = "thumbnail";

    private $config;

    private $imageManager;

    public function __construct(array $config = [])
    {
        $this->config = $this->mergeConfigWithDefault($config);
        $this->imageManager = new ImageManager([
            'driver' => $this->config['driver']
        ]);
    }

    public function peel($object, Closure $next)
    {
        if ($object->getType() !== 'image') {
            return $next($object);
        }

        $image = $this->imageManager->make($object->getPathname());

        $conf = $this->config;
        $method = $conf['method'];

        if ($method instanceof Closure) {
            $method($image, $conf);
        } else {
            switch($method) {
                default:
                    $args = [$conf['width'], $conf['height'], null, $conf['anchor']];
                    break;
                case 'fit':
                    $args = [$conf['width'], $conf['height']];
                    break;
                case 'resize':
                    $args = [$conf['width'], $conf['height']];
                    break;
                case 'heighten':
                    $args = [$conf['height']];
                    break;
                case 'widen':
                    $args = [$conf['widen']];
                    break;
                case 'resizeCanvas':
                    $args = [$conf['width'], $conf['height']];

                    if (isset($conf['anchor'])) {
                        $args[] = $conf['anchor'];
                    }
                    break;
            };

            call_user_func_array([$image, $method], $args);
        }

        $newName = $object->generateEditionFilename(self::editionKey);
        $newPath = sprintf('%s/%s', $object->getPath(), $newName);

        $edition = new Edition(self::editionKey, $newPath, $object->getUploaderPath(), [
            'type' => 'image',
            'height' => $conf['height'],
            'width' => $conf['width'],
            'crop' => $method instanceof Closure ? 'custom' : $method
        ], true);
        $image->save($edition);

        $object->addEdition($edition);

        return $next($object);
    }

    private function mergeConfigWithDefault(array $config)
    {
        return array_merge([
            'anchor' => 'center',
            'driver' => 'imagick',
            'height' => 100,
            'method' => 'fit',
            'width' => 100
        ], $config);
    }

}
