<?php

namespace Avtonom\Sms\StreamtelecomBundle\Provider;

use SmsSender\Exception as Exception;
use SmsSender\HttpAdapter\HttpAdapterInterface;
use SmsSender\Result\ResultInterface;
use SmsSender\Provider\AbstractProvider;
use SmsSender\Exception\ResponseException;
use Psr\Log\LoggerAwareTrait;

class StreamtelecomProvider extends AbstractProvider
{
    use LoggerAwareTrait;

    /**
     * @var string
     */
    const URL_AUTH = 'http://gateway.api.sc/rest/Session/session.php';
    const URL_SEND = 'http://gateway.api.sc/rest/Send/SendSms/';
    const URL_BALANCE = 'http://gateway.api.sc/rest/Balance/balance.php';
    const URL_SMS_STATUS = 'http://gateway.api.sc/rest/State/state.php';

    /**
     * @var string
     */
    protected $login;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $authToken;

    /**
     * @var array
     */
    protected $originators;

    /**
     * {@inheritDoc}
     */
    public function __construct(HttpAdapterInterface $adapter, $login, $password, array $originators = array())
    {
        parent::__construct($adapter);
        $this->login = $login;
        $this->password = $password;
        $this->originators = $originators;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Returns the HTTP adapter class for logger.
     *
     * @return string
     */
    public function getAdapterClass()
    {
        return get_class($this->adapter);
    }

    /**
     * Send a message to the given phone number.
     *
     * @param string $recipient  The phone number.
     * @param string $body       The message to send.
     * @param string $originator The name of the person which sends the message (from).
     *
     * @return array The data returned by the API.
     *
     * @throws Exception\InvalidArgumentException
     * @throws ResponseException
     */
    public function send($recipient, $body, $originator = '')
    {
        if (empty($originator)) {
            throw new Exception\InvalidArgumentException('The originator parameter is required for this provider.');
        }

        $params = $this->getParameters(array(
            'destinationAddress'    => $recipient,
            'data'  => $body,
            'sourceAddress'  => $originator,
        ));
        $this->getLogger()->addDebug('$params: '.print_r($params, true));

        $smsData = $this->executeQuery(self::URL_SEND, $params, array(
            'recipient'  => $recipient,
            'body'       => $body,
            'originator' => $originator,
        ));
        $smsData = $this->parseResponseSingleArray($smsData);
        $this->getLogger()->addDebug('Result: '.print_r($smsData, true));
        return $smsData;
    }

    /**
     * @param $messageId
     * @return array
     *
     * @throws ResponseException
     */
    public function getSmsStatus($messageId)
    {
        $params = $this->getParameters(array(
            'messageId' => $messageId,
        ));
        $smsData = $this->executeQuery(self::URL_SMS_STATUS, $params);
        if(empty($smsData['response']) || !isset($smsData['response']['State'])){
            throw new ResponseException('Response is empty');
        }
        $smsData['id'] = $messageId;
        switch($smsData['response']['State']){
            case -1:
                $smsData['status'] = ResultInterface::STATUS_SENT;
                break;
            case 0:
                $smsData['status'] = ResultInterface::STATUS_DELIVERED;
                break;
            case 42:
                $smsData['status'] = ResultInterface::STATUS_FAILED;
                break;
            case 46: // Expired (Lifetime expired messages)
                $smsData['status'] = ResultInterface::STATUS_FAILED;
                break;
            case 255: // incorrect message id
            default:
                throw new ResponseException(vsprintf('Unknown status "%s": "%s"', array($smsData['response']['State'], (!empty($smsData['response']['StateDescription'])?$smsData['response']['StateDescription']:''))));
        }
        return $smsData;
    }

    /**
     * @return array The data returned by the API.
     *
     * @throws ResponseException
     */
    public function getBalance()
    {
        $params = $this->getParameters([]);
        $smsData = $this->executeQuery(self::URL_BALANCE, $params);
        $smsData = $this->parseResponseSingle($smsData);
        return $smsData;
    }

    /**
     * @return string
     */
    public function getAuthToken()
    {
        if(!$this->authToken){
            $resultAuth = $this->executeQuery(self::URL_AUTH, array(
                'login'     => $this->login,
                'password'  => $this->password,
            ));
            $resultAuth = $this->parseResponseSingle($resultAuth);
            $this->authToken = $resultAuth['id'];
        }
        return $this->authToken;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'streamtelecom';
    }

    /**
     * @param string $url
     * @param array $data
     * @param array $extra_result_data
     *
     * @return array
     */
    protected function executeQuery($url, array $data = array(), array $extra_result_data = array())
    {
        $response = $this->getAdapter()->getContent($url, 'POST', array('Content-type: application/x-www-form-urlencoded'), $data);
        $this->getLogger()->addDebug(print_r($this->getAdapter()->getLastRequest(), true));
        $this->getLogger()->addDebug('Response: '.$response);
        $smsData = $this->parseResponse($response);
        $result = array_merge($this->getDefaults(), $extra_result_data, $smsData);
        $this->getLogger()->addDebug('Result base prepare response: '.print_r($result, true));
        return $result;
    }

    /**
     * Builds the parameters list to send to the API.
     *
     * @param array $additionnal_parameters
     *
     * @return array
     */
    public function getParameters(array $additionnal_parameters = array())
    {
        return array_merge(array(
            'sessionId'  => $this->getAuthToken(),
        ), $additionnal_parameters);
    }

    /**
     * Parse the data returned by the API.
     *
     * @param  string $response The raw result string.
     * @return array
     *
     * @throws ResponseException
     */
    protected function parseResponse($response)
    {
        if(empty($response)){
            return array();
        }
        $responseData = json_decode($response, true);
//        $this->getLogger()->addDebug('Response data: '.print_r($responseData, true));
        $smsData = array(
            'response' => $responseData
        );

        if(!empty($responseData['Code'])){
            $smsData['status'] = ResultInterface::STATUS_FAILED;
            $smsData['message'] = !empty($responseData['Desc']) ? $responseData['Desc'] : 'Result status code: '.$responseData['Code'];
            $responseException = new ResponseException($smsData['message'], $responseData['Code']);
            $responseException->getData($responseData);
            throw $responseException;
        }
        return $smsData;
    }

    /**
     * Parse the data returned by the API.
     *
     * @param  string $smsData The raw result string.
     * @return array
     *
     * @throws ResponseException
     */
    protected function parseResponseSingle($smsData)
    {
        if(!array_key_exists('response', $smsData) || (!is_string($smsData['response']) && !is_numeric($smsData['response']))){
            throw new ResponseException('Incorrect single value');
        }
        $smsData['id'] = $smsData['response'];
        $smsData['status'] = ResultInterface::STATUS_DELIVERED;
        return $smsData;
    }

    /**
     * Parse the data returned by the API.
     *
     * @param  string $smsData The raw result string.
     * @return array
     *
     * @throws ResponseException
     */
    protected function parseResponseSingleArray($smsData)
    {
        if(!array_key_exists('response', $smsData) || !is_array($smsData['response']) || !isset($smsData['response'][0])){
            throw new ResponseException('Incorrect single array value');
        }
        $smsData['id'] = $smsData['response'][0];
        $smsData['status'] = ResultInterface::STATUS_DELIVERED;
        return $smsData;
    }
}
