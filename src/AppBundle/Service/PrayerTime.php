<?php

namespace AppBundle\Service;

use AppBundle\Entity\Configuration;
use AppBundle\Entity\FlashMessage;
use AppBundle\Entity\Message;
use AppBundle\Entity\Mosque;
use Meezaan\PrayerTimes\Method;
use Psr\Log\LoggerInterface;
use Meezaan\PrayerTimes\PrayerTimes;

class PrayerTime
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $cacheDir;

    const METHOD_ALGERIA = 'ALGERIA';
    const METHOD_MOROCCO = 'MOROCCO';

    const DEFAULT_ADJUSTMENT = [
        PrayerTimes::METHOD_FRANCE => [-4, 5, 0, 3, 5],
        self::METHOD_ALGERIA => [1, 1, 2, 4, 2],
        self::METHOD_MOROCCO => [-8, 5, 0, 4, 0],
    ];

    const NEW_METHODS = [
        self::METHOD_ALGERIA => [18, 17],
        self::METHOD_MOROCCO => [18, 17],
    ];

    const ASR_METHOD_CHOICES = [
        PrayerTimes::SCHOOL_STANDARD,
        PrayerTimes::SCHOOL_HANAFI
    ];

    const HIGH_LATS_CHOICES = [
        PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_ANGLE,
        PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN,
        PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_ONESEVENTH
    ];

    const METHOD_CHOICES = [
        self::METHOD_ALGERIA,
        self::METHOD_MOROCCO,
        PrayerTimes::METHOD_FRANCE,
        PrayerTimes::METHOD_MWL,
        PrayerTimes::METHOD_ISNA,
        PrayerTimes::METHOD_MAKKAH,
        PrayerTimes::METHOD_EGYPT,
        PrayerTimes::METHOD_KARACHI,
        PrayerTimes::METHOD_RUSSIA,
        PrayerTimes::METHOD_CUSTOM
    ];

    public function __construct(LoggerInterface $logger, $cacheDir)
    {
        $this->logger = $logger;
        $this->cacheDir = $cacheDir;
    }

    /**
     * true if mosque or configuration has been updated
     * @param Mosque $mosque
     * @param string $lastUpdatedDate
     * @return string
     */
    function mosqueHasBeenUpdated(Mosque $mosque, $lastUpdatedDate)
    {
        return $mosque->getUpdated()->getTimestamp() > $lastUpdatedDate;
    }

    function getCalendar(Mosque $mosque)
    {
        $conf = $mosque->getConfiguration();

        if ($conf->isCalendar()) {
            $calendar = $conf->getCalendar();
            foreach ($calendar as $month => $days) {
                foreach ($days as $day => $prayers) {
                    $prayers = array_values($prayers);
                    $date = new \DateTime(date('Y') . '-' . ($month + 1) . '-' . $day . " 12:00:00", new \DateTimezone($conf->getTimezoneName()));
                    $this->applyDst($prayers, $mosque, $date);
                    $this->fixationProcess($prayers, $conf);
                    $calendar[$month][$day] = $prayers;
                }
            }
        }

        if ($conf->isApi()) {
            $calendar = [];

            $pt = new PrayerTimes();
            if ($conf->getPrayerMethod() !== PrayerTimes::METHOD_CUSTOM) {
                $pt = new PrayerTimes($conf->getPrayerMethod());
            }

            if (isset(self::NEW_METHODS[$conf->getPrayerMethod()])) {
                $method = new Method();
                $angle = self::NEW_METHODS[$conf->getPrayerMethod()];
                $method->setFajrAngle($angle[0]);
                $method->setIshaAngleOrMins($angle[1]);
                $pt = new PrayerTimes(PrayerTimes::METHOD_CUSTOM);
                $pt->setCustomMethod($method);
            }

            if ($conf->getPrayerMethod() === PrayerTimes::METHOD_CUSTOM) {
                $method = new Method();
                $method->setFajrAngle($conf->getFajrDegree());
                $method->setIshaAngleOrMins($conf->getIshaDegree());
                $pt = new PrayerTimes(PrayerTimes::METHOD_CUSTOM);
                $pt->setCustomMethod($method);
            }

            $pt->setAsrJuristicMethod($conf->getAsrMethod());
            $pt->setLatitudeAdjustmentMethod($conf->getHighLatsMethod());

            foreach (Calendar::MONTHS as $month => $days) {
                for ($day = 1; $day <= $days; $day++) {
                    $date = new \DateTime(date('Y') . '-' . ($month + 1) . '-' . $day . " 12:00:00", new \DateTimezone($conf->getTimezoneName()));
                    $this->applyAdjustment($pt, $mosque);
                    $prayers = $pt->getTimes($date, $mosque->getLatitude(), $mosque->getLongitude());
                    unset($prayers["Sunset"], $prayers["Imsak"], $prayers["Midnight"]);
                    $prayers = array_values($prayers);
                    $this->fixationProcess($prayers, $conf);
                    $calendar[$month][$day] = $prayers;
                }
            }
        }

        return $calendar;
    }

    private function applyAdjustment(PrayerTimes &$pt, Mosque $mosque)
    {
        $conf = $mosque->getConf();
        $defaultAdjustment = [0, 0, 0, 0, 0];

        if (isset(self::DEFAULT_ADJUSTMENT[$conf->getPrayerMethod()])) {
            $defaultAdjustment = self::DEFAULT_ADJUSTMENT[$conf->getPrayerMethod()];
        }

        $adjustment = $mosque->getConf()->getAdjustedTimes();

        foreach ($adjustment as $k => $v) {
            $v = (int)$v;
            $adjustment[$k] = $v !== 0 ? $v : $defaultAdjustment[$k];
        }

        $pt->tune(0, $adjustment[0], 0, $adjustment[1], $adjustment[2], $adjustment[3], 0, $adjustment[4]);
    }

    private function applyDst(&$prayers, Mosque $mosque, \DateTime $date)
    {
        $conf = $mosque->getConf();
        // dst disabled
        if ($conf->getDst() === 0) {
            return;
        }

        // dst programmed and not between selected dates
        if ($conf->getDst() === 1 && ($conf->getDstWinterDate() === null || $conf->getDstSummerDate() === null)) {
            return;
        }

        if ($conf->getDst() === 1 && ($date < $conf->getDstSummerDate() || $date > $conf->getDstWinterDate())) {
            return;
        }

        // dst auto and not in effect
        if ($conf->getDst() === 2 && $date->format("I") === "0") {
            return;
        }

        foreach ($prayers as $k => $prayer) {
            try {
                $prayers[$k] = ((new \DateTime($prayer))->modify("+1 hour"))->format("H:i");
            } catch (\Exception $e) {
                $prayers[$k] = "ERROR";
                $this->logger->error("Erreur de parsing heure de prière", [$e, $mosque->getId()]);
            }
        }
    }

    private function fixationProcess(array &$prayers, Configuration $conf)
    {
        $fixations = $conf->getFixedTimes();
        $fixations = [$fixations[0], null, $fixations[1], $fixations[2], $fixations[3], $fixations[4]];

        foreach ($fixations as $k => $fixation) {
            // adjust isha to x min after maghrib if option enabled
            if ($k === 5 && is_numeric($conf->getIshaFixation())) {
                try {
                    $prayers[5] = (new \DateTime($prayers[4]))->modify($conf->getIshaFixation() . "minutes")->format("H:i");;
                } catch (\Exception $e) {
                    $prayers[$k] = "ERROR";
                    $this->logger->error("Erreur de parsing heure de prière", [$e]);
                }
            }

            if (!empty($fixation) && $fixation > $prayers[$k]) {
                $prayers[$k] = $fixation;
            }
        }
        return $prayers;
    }

    /**
     * transforme json calendar in csv files and compress theme in a zip file
     * @param Mosque $mosque
     * @return string the path of the zip file
     */
    function getFilesFromCalendar(Mosque $mosque)
    {
        $calendar = $this->getCalendar($mosque);

        if (is_array($calendar)) {
            $path = $this->cacheDir . "/" . $mosque->getId();
            if (!is_dir($path)) {
                mkdir($path);
            }
            foreach ($calendar as $key => $monthIndex) {
                $str = "Day,Fajr,Shuruk,Duhr,Asr,Maghrib,Isha\n";
                foreach ($monthIndex as $day => $prayers) {
                    $str .= "$day," . implode(",", $prayers) . "\n";
                }
                $fileName = str_pad($key + 1, 2, "0", STR_PAD_LEFT) . ".csv";
                file_put_contents("$path/$fileName", $str);
            }

            return $this->getZipFile($mosque, $path);
        }

        return null;
    }

    private function getZipFile(Mosque $mosque, $path)
    {
        $zip = new \ZipArchive();
        $zipFileName = $mosque->getSlug() . ".zip";
        $zipFilePath = "$path/" . $zipFileName;

        if ($zip->open($zipFilePath, \ZipArchive::CREATE) === true) {
            $files = scandir("$path");
            foreach ($files as $file) {
                if (strpos($file, ".") !== 0) {
                    $zip->addFile("$path/$file", $file);
                }
            }
            $zip->close();
            array_map('unlink', glob("$path/*.csv"));
        }
        return $zipFilePath;
    }

    /**
     *  Get pray times and other info of the mosque
     * @param Mosque $mosque
     * @param bool $returnFullCalendar
     * @return array
     */
    public function prayTimes(Mosque $mosque, $returnFullCalendar = false)
    {
        $conf = $mosque->getConfiguration();
        $flashMessage = $mosque->getFlashMessage();

        $result = [
            'id' => $mosque->getId(),
            'name' => $mosque->getTitle(),
            'localisation' => $mosque->getLocalisation(),
            'phone' => $mosque->getPhone(),
            'email' => $mosque->getEmail(),
            'site' => $mosque->getSite(),
            'association' => $mosque->getAssociationName(),
            'image' => $mosque->getImage1() ? 'https://mawaqit.net/upload/' . $mosque->getImage1() : null,
            'url' => 'https://mawaqit.net/fr/' . $mosque->getSlug(),
            'latitude' => $mosque->getLatitude(),
            'longitude' => $mosque->getLongitude(),
            'hijriAdjustment' => $conf->getHijriAdjustment(),
            'aidPrayerTime' => $conf->getAidTime(),
            'jumua' => $conf->isNoJumua() ? null : $conf->getJumuaTime(),
            'jumua2' => $conf->isNoJumua() ? null : $conf->getJumuaTime2(),
            'jumuaAsDuhr' => $conf->isJumuaAsDuhr(),
            'imsakNbMinBeforeFajr' => $conf->getImsakNbMinBeforeFajr(),
            'maximumIshaTimeForNoWaiting' => $conf->getMaximumIshaTimeForNoWaiting(),
            'shuruq' => null,
            'times' => null,
            'iqama' => $conf->getWaitingTimes(),
            'womenSpace' => $mosque->getWomenSpace(),
            'janazaPrayer' => $mosque->getJanazaPrayer(),
            'aidPrayer' => $mosque->getAidPrayer(),
            'childrenCourses' => $mosque->getChildrenCourses(),
            'adultCourses' => $mosque->getAdultCourses(),
            'ramadanMeal' => $mosque->getRamadanMeal(),
            'handicapAccessibility' => $mosque->getHandicapAccessibility(),
            'ablutions' => $mosque->getAblutions(),
            'parking' => $mosque->getParking(),
            'otherInfo' => $mosque->getOtherInfo(),
            'flashMessage' => $flashMessage instanceof FlashMessage && $flashMessage->isAvailable() ? $flashMessage->getContent() : null,
            'announcements' => $this->getMessages($mosque),
            'updatedAt' => $mosque->getUpdated()->getTimestamp(),
        ];

        // add flash message
        if ($flashMessage instanceof FlashMessage && $flashMessage->isAvailable()) {
            $result['flash'] = [
                'content' => $flashMessage->getContent(),
                'expire' => $flashMessage->getExpire()->getTimestamp()
            ];
        }

        $calendar = $this->getCalendar($mosque);

        if ($returnFullCalendar) {
            $result['calendar'] = $calendar;
            $result['iqamaCalendar'] = $conf->getIqamaCalendar();
        }

        $times = $this->getPrayTimes($calendar);
        $result['shuruq'] = $times[1];
        unset($times[1]);

        $result['times'] = array_values($times);

        $result['fixedIqama'] = $this->getFixedIqama($mosque, $result['times']);

        return $result;
    }

    private function getPrayTimes($calendar)
    {
        $date = new \DateTime();
        $month = $date->format('m') - 1;
        $day = (int)$date->format('d');
        return $calendar[$month][$day];
    }

    private function getFixedIqama(Mosque $mosque, $prayerTimes)
    {
        $conf = $mosque->getConf();
        $fixedIqama = ["", "", "", "", ""];

        if (is_array($conf->getIqamaCalendar())) {
            $date = new \DateTime();
            $month = $date->format('m') - 1;
            $day = (int)$date->format('d');
            $iqamaFromCalendar = array_values($conf->getIqamaCalendar()[$month][$day]);

            foreach ($iqamaFromCalendar as $k => $v) {
                if (!empty($v) && $v > $prayerTimes[$k]) {
                    $fixedIqama[$k] = $v;
                }
            }
        }

        foreach ($conf->getFixedIqama() as $k => $v) {
            if (!empty($v) && $v > $prayerTimes[$k]) {
                $fixedIqama[$k] = $v;
            }
        }

        return $fixedIqama;
    }

    private function getMessages(Mosque $mosque)
    {
        $messages = [];
        /**
         * @var Message $message
         */
        foreach ($mosque->getMessages() as $message) {
            if ($message->isEnabled() && $message->isMobile()) {
                $messages[] = [
                    'id' => $message->getId(),
                    'title' => $message->getTitle(),
                    'content' => $message->getContent(),
                    'isMobile' => $message->isMobile(),
                    'isDesktop' => $message->isDesktop(),
                    'image' => $message->getImage() ? 'https://mawaqit.net/upload/' . $message->getImage() : null,
                ];
            }
        }
        return $messages;
    }
}
