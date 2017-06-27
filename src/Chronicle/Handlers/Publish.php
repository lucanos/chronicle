<?php
namespace ParagonIE\Chronicle\Handlers;

use ParagonIE\Chronicle\Chronicle;
use ParagonIE\Chronicle\Exception\ClientNotFound;
use ParagonIE\Chronicle\HandlerInterface;
use ParagonIE\Sapient\Sapient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Request;

/**
 * Class Publish
 * @package ParagonIE\Chronicle\Handlers
 */
class Publish implements HandlerInterface
{
    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     * @throws \Error
     * @throws \TypeError
     */
    public function __invoke(
        RequestInterface $request,
        ResponseInterface $response,
        array $args = []
    ): ResponseInterface {
        if ($request instanceof Request) {
            if (!$request->getAttribute('authenticated')) {
                throw new \Error('Unauthenticated request');
            }
        } else {
            throw new \TypeError('Something unexpected happen when attempting to publish.');
        }

        // Get the public key and signature; store this information:
        list($publicKey, $signature) = $this->getHeaderData($request);

        $result = Chronicle::extendBlakechain(
            (string) $request->getBody(),
            $signature,
            $publicKey
        );

        return Chronicle::getSapient()->createSignedJsonResponse(
            200,
            [
                'datetime' => (new \DateTime())->format(\DateTime::ATOM),
                'status' => 'OK',
                'results' => $result
            ],
            Chronicle::getSigningKey(),
            $response->getHeaders(),
            $response->getProtocolVersion()
        );
    }

    /**
     * Get the SigningPublicKey and signature for this message.
     *
     * @param RequestInterface $request
     * @return array
     * @throws ClientNotFound
     * @throws \Error
     */
    public function getHeaderData(RequestInterface $request): array
    {
        $clientHeader = $request->getHeader(Chronicle::CLIENT_IDENTIFIER_HEADER);
        if (!$clientHeader) {
            throw new \Error('No client header provided');
        }
        $signatureHeader = $request->getHeader(Sapient::HEADER_SIGNATURE_NAME);
        if (!$signatureHeader) {
            throw new \Error('No signature provided');
        }

        if (\count($signatureHeader) === 1 && count($clientHeader) === 1) {
            return [
                Chronicle::getClientsPublicKey(\array_shift($clientHeader)),
                \array_shift($signatureHeader)
            ];
        }
        throw new ClientNotFound('Could not find the correct client');
    }
}
