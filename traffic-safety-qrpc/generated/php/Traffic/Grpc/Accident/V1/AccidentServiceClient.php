<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Traffic\Grpc\Accident\V1;

/**
 */
class AccidentServiceClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Google\Protobuf\GPBEmpty $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall
     */
    public function All(\Google\Protobuf\GPBEmpty $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/traffic.accident.v1.AccidentService/All',
        $argument,
        ['\Traffic\Grpc\Accident\V1\AllAccidentsResponse', 'decode'],
        $metadata, $options);
    }

}
