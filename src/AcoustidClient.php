<?php

namespace AcoustidApi;

use AcoustidApi\DataCompressor\GzipCompressor;
use AcoustidApi\Exceptions\AcoustidException;
use AcoustidApi\RequestModel\{
    FingerPrint, FingerPrintCollection, FingerPrintCollectionNormalizer
};
use AcoustidApi\ResponseModel\Collection\{CollectionModel, MBIdCollection, SubmissionCollection, TrackCollection};
use AcoustidApi\ResponseModel\Collection\ResultCollection;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

class AcoustidClient
{
    const FORMAT = 'json';

    /** @var null|string */
    private $apiKey;

    /** @var ResponseProcessor */
    private $responseProcessor;

    /**
     * AcoustidClient constructor.
     *
     * @param null|string $apiKey
     */
    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey;
        $this->responseProcessor = new ResponseProcessor();
    }

    /**
     * @throws AcoustidException
     */
    private function checkApiKey(): void
    {
        if ($this->apiKey === null) {
            throw new AcoustidException('You need to set api key to use this method');
        }
    }

    /**
     * @return Request
     */
    protected function createRequest(): Request
    {
        return new Request($this->apiKey);
    }


    /**
     * @param string $apiKey
     */
    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param FingerPrint $fingerPrint
     * @param array $meta - all meta values are available in AcoustidApi/Meta class
     *
     * @return ResultCollection
     * @throws AcoustidException
     */
    public function lookupByFingerPrint(FingerPrint $fingerPrint, array $meta = []): ResultCollection
    {
        $this->checkApiKey();
        $response = $this->createRequest()
            ->addParameters([
                    'fingerprint' => $fingerPrint->getFingerprint(),
                    'duration' => $fingerPrint->getDuration(),
                    'meta' => $meta,
                    'format' => self::FORMAT,
                ]
            )->sendCompressedPost(new GzipCompressor(), Actions::LOOKUP);

        return $this->responseProcessor->process($response, ResultCollection::class, self::FORMAT);
    }

    /**
     * @param string $trackId
     * @param array $meta - all meta values are available in AcoustidApi/Meta class
     *
     * @return ResultCollection
     * @throws AcoustidException
     */
    public function lookupByTrackId(string $trackId, array $meta = []): ResultCollection
    {
        $this->checkApiKey();
        $response = $this->createRequest()
            ->addParameters([
                    'trackid' => $trackId,
                    'meta' => $meta,
                    'format' => self::FORMAT,
                ]
            )->sendGet(Actions::LOOKUP);

        return $this->responseProcessor->process($response, ResultCollection::class, self::FORMAT);
    }

    /**
     * @param FingerPrintCollection $fingerPrints
     * @param string $userApiKey - users's API key
     * @param string $clientVersion - application's version, default '1.0'
     * @param int $wait - wait up to $wait seconds for the submission(s) to import, default 1
     *
     * @return SubmissionCollection
     * @throws AcoustidException
     */
    public function submit(FingerPrintCollection $fingerPrints, string $userApiKey, string $clientVersion = '1.0', int $wait = 1): SubmissionCollection
    {
        $this->checkApiKey();

        $serializer = new Serializer([new FingerPrintCollectionNormalizer()]);
        $normalizedFingerPrints = $serializer->normalize($fingerPrints);
        $response = $this->createRequest()
            ->addParameters([
                'user' => $userApiKey,
                'clientversion' => $clientVersion,
                'wait' => $wait,
            ])
            ->addParameters($normalizedFingerPrints)
            ->sendCompressedPost(new GzipCompressor(), Actions::SUBMIT);

        return $this->responseProcessor->process($response, SubmissionCollection::class, self::FORMAT);
    }

    /**
     * @param int $submissionId
     * @param string $clientVersion
     *
     * @return SubmissionCollection
     * @throws AcoustidException
     */
    public function getSubmissionStatus(int $submissionId, string $clientVersion = '1.0'): SubmissionCollection
    {
        $this->checkApiKey();
        $response = $this->createRequest()
            ->addParameters([
                'id' => $submissionId,
                'clientversion' => $clientVersion,
                'format' => self::FORMAT
            ])
            ->sendGet(Actions::SUBMISSION_STATUS);

        return $this->responseProcessor->process($response, SubmissionCollection::class, self::FORMAT);
    }

    /**
     * @param string $mdid
     * @param bool $batch
     *
     * @return CollectionModel
     * @throws AcoustidException
     */
    public function listByMBID(string $mdid, bool $batch = false): CollectionModel
    {
        $response = $this->createRequest()
            ->addParameters([
                'mbid' => $mdid,
                'batch' => (int)$batch,
            ])->sendGet(Actions::TRACKLIST_BY_MBID);

        $resultType = $batch ? MBIdCollection::class : TrackCollection::class;

        return $this->responseProcessor->process($response, $resultType, self::FORMAT);
    }

}
