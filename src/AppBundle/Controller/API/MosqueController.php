<?php

namespace AppBundle\Controller\API;

use AppBundle\Entity\Mosque;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;

/**
 * @Route("/api/1.0.0/mosque", options={"i18n"="false"})
 */
class MosqueController extends Controller
{
    /**
     * @Route("/search")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse
     * @throws \Doctrine\DBAL\DBALException
     */
    public function searchAction(Request $request)
    {
        $word = $request->query->get('word');
        $lat = $request->query->get('lat');
        $lon = $request->query->get('lon');
        $result = $this->get('app.mosque_service')->searchApi($word, $lat, $lon);
        return new JsonResponse($result);
    }

    /**
     * Get all data of mosque
     * @Route("/{id}")
     * @Method("GET")
     * @param Mosque $mosque
     * @return Response
     */
    public function dataAction(Mosque $mosque)
    {
        if ($mosque->isHome()) {
            throw new NotFoundHttpException();
        }

        $normalizer = new ObjectNormalizer();
        $normalizer->setIgnoredAttributes(['user', 'id', 'created', 'updated', 'messages', 'image1', 'image2', 'image3', 'localisation', 'justificatory',
            'nbOfEnabledMessages', 'calendarCompleted', 'gpsCoordinates', 'title', 'types', 'synchronized', 'slug', 'locale', 'flashMessage', 'status']);

        $normalizer->setCircularReferenceHandler(function ($mosque) {
            return $mosque->getId();
        });

        $serializer = new Serializer([new DateTimeNormalizer(), $normalizer], [new JsonEncoder()]);
        $mosque->setSite($mosque->getUrl());
        $result = $serializer->serialize($mosque, 'json');
        return new Response($result, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    /**
     * Get pray times and other info of the mosque by ID
     * @Route("/{mosque}/prayer-times")
     * @Cache(public=true, maxage="300")
     * @Method("GET")
     * @param Request $request
     * @param Mosque $mosque
     * @return Response
     */
    public function prayTimesAction(Request $request, Mosque $mosque)
    {

        if ($mosque->isHome()) {
            throw new NotFoundHttpException();
        }

        if ($request->query->has('updatedAt')) {
            $updatedAt = $request->query->get('updatedAt');
            if (!is_numeric($updatedAt)) {
                throw new BadRequestHttpException();
            }

            if ($mosque->getUpdated()->getTimestamp() <= $updatedAt) {
                return new Response(null, Response::HTTP_NOT_MODIFIED);
            }
        }

        $response = new JsonResponse();
        $response->setLastModified($mosque->getUpdated());

        if ($response->isNotModified($request)) {
            return $response;
        }

        $calendar = $request->query->has('calendar');
        $result = $this->get('app.prayer_times')->prayTimes($mosque, $calendar);
        $response->setData($result);

        return $response;
    }

}
