<?php

namespace Pumukit\PaellaPlayerBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Pumukit\BasePlayerBundle\Controller\BasePlayerController as BasePlayerControllero;

class BasePlayerController extends BasePlayerControllero
{
    /**
     * @Route("/videoplayer/{id}", name="pumukit_videoplayer_index", defaults={"show_block": true, "no_channels": true, "track": false})
     * @Route("/videoplayer/opencast/{id}", name="pumukit_videoplayer_opencast", defaults={"show_block": true, "no_channels": true, "track": false})
     * @Template("PumukitPaellaPlayerBundle:PaellaPlayer:player.html.twig")
     *
     * @param Request          $request
     * @param MultimediaObject $multimediaObject
     *
     * @return array|mixed|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function indexAction(Request $request, MultimediaObject $multimediaObject)
    {
        $request = $this->container->get('request_stack')->getMasterRequest();

        $playerService = $this->get('pumukit_baseplayer.player');
        $canBeReproduced = $playerService->canBeReproduced($multimediaObject, false);
        if (!$canBeReproduced) {
            return [
                'object' => $multimediaObject,
            ];
        }

        return $this->doRender($request, $multimediaObject, false);
    }

    /**
     * @Route("/videoplayer/magic/{secret}", name="pumukit_videoplayer_magicindex", defaults={"show_block": true, "no_channels": true, "track": false})
     * @Template("PumukitPaellaPlayerBundle:PaellaPlayer:player.html.twig")
     *
     * @param Request          $request
     * @param MultimediaObject $multimediaObject
     *
     * @return array|mixed|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function magicAction(Request $request, MultimediaObject $multimediaObject)
    {
        if (!$request->query->has('secret')) {
            return $this->redirect($this->generateUrl('pumukit_videoplayer_magicindex', array(
                'id' => $multimediaObject->getId(),
                'secret' => $multimediaObject->getSecret(),
                )).'&secret='.$multimediaObject->getSecret()
            );
        }

        $playerService = $this->get('pumukit_baseplayer.player');
        $canBeReproduced = $playerService->canBeReproduced($multimediaObject, true);
        if (!$canBeReproduced) {
            return [
                'object' => $multimediaObject,
            ];
        }

        return $this->doRender($request, $multimediaObject, true);
    }

    /**
     * @param Request          $request
     * @param MultimediaObject $multimediaObject
     * @param bool             $isMagicUrl
     *
     * @return array|bool|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function doRender(Request $request, MultimediaObject $multimediaObject, $isMagicUrl = false)
    {
        $embeddedBroadcastService = $this->get('pumukitschema.embeddedbroadcast');
        $password = $request->get('broadcast_password');
        $response = $embeddedBroadcastService->canUserPlayMultimediaObject($multimediaObject, $this->getUser(), $password);
        if ($response instanceof Response) {
            return $response;
        }

        $track = $request->query->has('track_id') ? $multimediaObject->getTrackById($request->query->get('track_id')) : $multimediaObject->getDisplayTrack();
        if ($track && $track->containsTag('download')) {
            return $this->redirect($track->getUrl());
        }

        if ($url = $multimediaObject->getProperty('externalplayer')) {
            return $this->redirect($url);
        }

        if ($request->query->has('raw')) {
            return $this->render('PumukitPaellaPlayerBundle:BasePlayer:player.html.twig', array(
                'autostart' => $this->getAutoStart($request),
                'autoplay_fallback' => $this->container->getParameter('pumukitpaella.autoplay'),
                'when_dispatch_view_event' => $this->getParameterWithDefaultValue('pumukitplayer.when_dispatch_view_event', 'on_load'),
                'multimediaObject' => $multimediaObject,
                'track' => $track,
            ));
        }

        if (!$track && $multimediaObject->isMultistream()) {
            $tracks = $multimediaObject->getFilteredTracksWithTags(array('presenter/delivery', 'presentation/delivery'));
        } else {
            $tracks = array($track);
        }

        return array(
            'autostart' => $this->getAutoStart($request),
            'autoplay_fallback' => $this->container->getParameter('pumukitpaella.autoplay'),
            'intro' => $this->get('pumukit_baseplayer.intro')->getIntroForMultimediaObject($request->query->get('intro'), $multimediaObject->getProperty('intro')),
            'custom_css_url' => $this->container->getParameter('pumukitpaella.custom_css_url'),
            'logo' => $this->container->getParameter('pumukitpaella.logo'),
            'multimediaObject' => $multimediaObject,
            'object' => $multimediaObject,
            'when_dispatch_view_event' => $this->getParameterWithDefaultValue('pumukitplayer.when_dispatch_view_event', 'on_load'),
            'tracks' => $tracks,
            'opencast_host' => $this->getParameterWithDefaultValue('pumukit_opencast.host', ''),
            'magic_url' => $isMagicUrl,
        );
    }

    /**
     * @param $request
     *
     * @return bool
     */
    private function getAutoStart($request)
    {
        if ('disabled' === $this->container->getParameter('pumukitpaella.autoplay')) {
            return false;
        }

        $autoStart = $request->query->get('autostart', 'false');
        $userAgent = $request->headers->get('user-agent');
        if (false !== strpos($userAgent, 'Safari')) {
            if (false === strpos($userAgent, 'Chrome')) {
                $autoStart = false;
            }
        }

        return $autoStart;
    }

    /**
     * @param      $name
     * @param null $default
     *
     * @return mixed|null
     */
    private function getParameterWithDefaultValue($name, $default = null)
    {
        if ($this->container->hasParameter($name)) {
            return $this->container->getParameter($name);
        }

        return $default;
    }
}
