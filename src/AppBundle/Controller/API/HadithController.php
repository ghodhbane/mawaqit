<?php

namespace AppBundle\Controller\API;

use AppBundle\Entity\Configuration;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;

/**
 * @Route("/api/1.0.0/hadith")
 */
class HadithController extends Controller
{

    /**
     * @Route("/random", name="random_hadith", options={"i18n"="false"})
     * @Cache(public=true, maxage="1800")
     * @Method("GET")
     * @param Request $request
     * @return Response
     */
    public function randomAction(Request $request)
    {
        $lang = $request->query->get('lang');
        $maxLength = $request->query->get('maxLength', 500);
        if (!in_array($lang, Configuration::HADITH_LANG)) {
            $lang = 'ar';
        }

        if (strpos($lang, "-") !== false) {
            $languages = explode('-', $lang);
            $lang = $languages[array_rand($languages)];
        }

        $file = $this->getParameter("kernel.root_dir") . "/Resources/xml/ahadith-$lang.xml";
        $xmldata = simplexml_load_file($file);
        $ahadith = $xmldata->xpath('hadith');
        $hadith = $this->getRandomHadith($ahadith, $maxLength);

        $reponse = [
            "text" => preg_replace('/\s+/', ' ', preg_replace('/\n+/', '', (string)$hadith)),
            "lang" => $lang
        ];
        return new JsonResponse($reponse);
    }

    private function getRandomHadith(array $ahadith, $maxLength)
    {
        $hadith = $ahadith[array_rand($ahadith)];
        if (strlen($hadith) < 10 || strlen($hadith) > (int)$maxLength) {
            return $this->getRandomHadith($ahadith, $maxLength);
        }
        return $hadith;
    }

}
