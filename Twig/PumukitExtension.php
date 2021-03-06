<?php

namespace Pumukit\PaellaPlayerBundle\Twig;

use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Component\HttpFoundation\Request;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PumukitExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getPaellaLayout', [$this, 'getPaellaLayout', ['needs_environment' => true]]),
        ];
    }

    public function getPaellaLayout(MultimediaObject $multimediaObject, Request $request)
    {
        $paellaLayout = 'professor_slide';

        if ($multimediaObject->getProperty('opencastinvert')) {
            $paellaLayout = 'slide_professor';
        }

        if ($multimediaObject->getProperty('paellalayout')) {
            $paellaLayout = $multimediaObject->getProperty('paellalayout');
        }

        return $request->query->get('paella_layout', $paellaLayout);
    }
}
