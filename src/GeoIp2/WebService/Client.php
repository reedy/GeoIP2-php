<?php

namespace GeoIp2\WebService;

use GeoIp2\Exception\GeoIp2Exception;
use GeoIp2\Exception\HttpException;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Exception\AuthenticationException;
use GeoIp2\Exception\InvalidRequestException;
use GeoIp2\Exception\OutOfQueriesException;
use GeoIp2\Model\City;
use GeoIp2\Model\CityIspOrg;
use GeoIp2\Model\Country;
use GeoIp2\Model\Omni;
use Guzzle\Http\Client as GuzzleClient;
use Guzzle\Common\Exception\RuntimeException;
use Guzzle\Http\Exception\ClientErrorResponseException;
use Guzzle\Http\Exception\ServerErrorResponseException;

/**
 * This class provides a client API for all the GeoIP2 web service's
 * end points. The end points are Country, City, City/ISP/Org, and Omni. Each
 * end point returns a different set of data about an IP address, with Country
 * returning the least data and Omni the most.
 *
 * Each web service end point is represented by a different model class, and
 * these model classes in turn contain multiple Record classes. The record
 * classes have attributes which contain data about the IP address.
 *
 * If the web service does not return a particular piece of data for an IP
 * address, the associated attribute is not populated.
 *
 * The web service may not return any information for an entire record, in
 * which case all of the attributes for that record class will be empty.
 *
 * **Usage**
 *
 * The basic API for this class is the same for all of the web service end
 * points. First you create a web service object with your MaxMind
 * <code>$userId</code> and <code>$licenseKey</code>, then you call the method
 * corresponding to a specific end point, passing it the IP address you want
 * to look up.
 *
 * If the request succeeds, the method call will return a model class for
 * the end point you called. This model in turn contains multiple record
 * classes, each of which represents part of the data returned by the web
 * service.
 *
 * If the request fails, the client class throws an exception.
 *
 * **Exceptions**
 *
 * For details on the possible errors returned by the web service itself, see
 * {@link http://dev.maxmind.com/geoip2/geoip/web-services the GeoIP2 web
 * service docs}.
 *
 * If the web service returns an explicit error document, this is thrown as a
 * {@link \GeoIp2\Exception\WebServiceException}. If some other sort of
 * transport error occurs, this is thrown as a {@link
 * \GeoIp2\Exception\HttpException}. The difference is that the web service
 * error includes an error message and error code delivered by the web
 * service. The latter is thrown when some sort of unanticipated error occurs,
 * such as the web service returning a 500 or an invalid error document.
 *
 * If the web service returns any status code besides 200, 4xx, or 5xx, this
 * also becomes a {@link \GeoIp2\Exception\HttpException}.
 *
 * Finally, if the web service returns a 200 but the body is invalid, the
 * client throws a {@link \GeoIp2\Exception\GeoIp2Exception}.
 */
class Client
{
    private $userId;
    private $licenseKey;
    private $languages;
    private $host;
    private $guzzleClient;

    /**
     * Constructor.
     *
     * @param int    $userId     Your MaxMind user ID
     * @param string $licenseKey Your MaxMind license key
     * @param array  $languages  List of language codes to use in name property
     * from most preferred to least preferred.
     * @param string $host Optional host parameter
     * @param object $guzzleClient Optional Guzzle client to use (to facilitate
     * unit testing).
     */
    public function __construct(
        $userId,
        $licenseKey,
        $languages = array('en'),
        $host = 'geoip.maxmind.com',
        $guzzleClient = null
    ) {
        $this->userId = $userId;
        $this->licenseKey = $licenseKey;
        $this->languages = $languages;
        $this->host = $host;
        // To enable unit testing
        $this->guzzleClient = $guzzleClient;
    }

    /**
     * This method calls the GeoIP2 City endpoint.
     *
     * @param string $ipAddress IPv4 or IPv6 address as a string. If no
     * address is provided, the address that the web service is called
     * from will be used.
     *
     * @return \GeoIp2\Model\City
     *
     * @throws \GeoIp2\Exception\GeoIp2Exception if there was a generic
     * error processing your request.
     * @throws \GeoIp2\Exception\HttpException if there was an HTTP transport
     * error.
     * @throws \GeoIp2\Exception\WebServiceException if an error was returned
     * by MaxMind's GeoIP2 web service.
     */
    public function city($ipAddress = 'me')
    {
        return $this->responseFor('city', 'City', $ipAddress);
    }

    /**
     * This method calls the GeoIP2 Country endpoint.
     *
     * @param string $ipAddress IPv4 or IPv6 address as a string. If no
     * address is provided, the address that the web service is called
     * from will be used.
     *
     * @return \GeoIp2\Model\Country
     *
     * @throws \GeoIp2\Exception\GeoIp2Exception if there was a generic
     * error processing your request.
     * @throws \GeoIp2\Exception\HttpException if there was an HTTP transport
     * error.
     * @throws \GeoIp2\Exception\WebServiceException if an error was returned
     * by MaxMind's GeoIP2 web service.
     */
    public function country($ipAddress = 'me')
    {
        return $this->responseFor('country', 'Country', $ipAddress);
    }

    /**
     * This method calls the GeoIP2 City/ISP/Org endpoint.
     *
     * @param string $ipAddress IPv4 or IPv6 address as a string. If no
     * address is provided, the address that the web service is called
     * from will be used.
     *
     * @return \GeoIp2\Model\CityIspOrg
     *
     * @throws \GeoIp2\Exception\GeoIp2Exception if there was a generic
     * error processing your request.
     * @throws \GeoIp2\Exception\HttpException if there was an HTTP transport
     * error.
     * @throws \GeoIp2\Exception\WebServiceException if an error was returned
     * by MaxMind's GeoIP2 web service.
     */
    public function cityIspOrg($ipAddress = 'me')
    {
        return $this->responseFor('city_isp_org', 'CityIspOrg', $ipAddress);
    }

    /**
     * This method calls the GeoIP2 Omni endpoint.
     *
     * @param string $ipAddress IPv4 or IPv6 address as a string. If no
     * address is provided, the address that the web service is called
     * from will be used.
     *
     * @return \GeoIp2\Model\Omni
     *
     * @throws \GeoIp2\Exception\GeoIp2Exception if there was a generic
     * error processing your request.
     * @throws \GeoIp2\Exception\HttpException if there was an HTTP transport
     * error.
     * @throws \GeoIp2\Exception\WebServiceException if an error was returned
     * by MaxMind's GeoIP2 web service.
     */
    public function omni($ipAddress = 'me')
    {
        return $this->responseFor('omni', 'Omni', $ipAddress);
    }

    private function responseFor($endpoint, $class, $ipAddress)
    {
        $uri = implode('/', array($this->baseUri(), $endpoint, $ipAddress));

        $client = $this->guzzleClient ?
            $this->guzzleClient : new GuzzleClient();
        $request = $client->get($uri, array('Accept' => 'application/json'));
        $request->setAuth($this->userId, $this->licenseKey);
        $ua = $request->getHeader('User-Agent');
        $ua = "GeoIP2 PHP API ($ua)";
        $request->setHeader('User-Agent', $ua);

        $response = null;
        try {
            $response = $request->send();
        } catch (ClientErrorResponseException $e) {
            $this->handle4xx($e->getResponse(), $uri);
        } catch (ServerErrorResponseException $e) {
            $this->handle5xx($e->getResponse(), $uri);
        }

        if ($response && $response->isSuccessful()) {
            $body = $this->handleSuccess($response, $uri);
            $class = "GeoIp2\\Model\\" . $class;
            return new $class($body, $this->languages);
        } else {
            $this->handleNon200($response, $uri);
        }
    }

    private function handleSuccess($response, $uri)
    {
        if ($response->getContentLength() == 0) {
            throw new GeoIp2Exception(
                "Received a 200 response for $uri but did not " .
                "receive a HTTP body."
            );
        }

        try {
            return $response->json();
        } catch (RuntimeException $e) {
            throw new GeoIp2Exception(
                "Received a 200 response for $uri but could not decode " .
                "the response as JSON: " . $e->getMessage()
            );

        }
    }

    private function handle4xx($response, $uri)
    {
        $status = $response->getStatusCode();

        $body = array();

        if ($response->getContentLength() > 0) {
            if (strstr($response->getContentType(), 'json')) {
                try {
                    $body = $response->json();
                    if (!isset($body['code']) || !isset($body['error'])) {
                        throw new GeoIp2Exception(
                            'Response contains JSON but it does not specify ' .
                            'code or error keys: ' . $response->getBody()
                        );
                    }
                } catch (RuntimeException $e) {
                    throw new HttpException(
                        "Received a $status error for $uri but it did not " .
                        "include the expected JSON body: " .
                        $e->getMessage(),
                        $status,
                        $uri
                    );
                }
            } else {
                throw new HttpException(
                    "Received a $status error for $uri with the " .
                    "following body: " . $response->getBody(),
                    $status,
                    $uri
                );
            }
        } else {
            throw new HttpException(
                "Received a $status error for $uri with no body",
                $status,
                $uri
            );
        }
        $this->handleWebServiceError(
            $body['error'],
            $body['code'],
            $status,
            $uri
        );
    }

    private function handleWebServiceError($message, $code, $status, $uri)
    {
        switch ($code) {
            case 'IP_ADDRESS_NOT_FOUND':
            case 'IP_ADDRESS_RESERVED':
                throw new AddressNotFoundException($message);
            case 'AUTHORIZATION_INVALID':
            case 'LICENSE_KEY_REQUIRED':
            case 'USER_ID_REQUIRED':
                throw new AuthenticationException($message);
            case 'OUT_OF_QUERIES':
                throw new OutOfQueriesException($message);
            default:
                throw new InvalidRequestException(
                    $message,
                    $code,
                    $status,
                    $uri
                );
        }
    }

    private function handle5xx($response, $uri)
    {
        $status = $response->getStatusCode();

        throw new HttpException(
            "Received a server error ($status) for $uri",
            $status,
            $uri
        );
    }

    private function handleNon200($response, $uri)
    {
        $status = $response->getStatusCode();

        throw new HttpException(
            "Received a very surprising HTTP status " .
            "($status) for $uri",
            $status,
            $uri
        );
    }

    private function baseUri()
    {
        return 'https://' . $this->host . '/geoip/v2.0';
    }
}