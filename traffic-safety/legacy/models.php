<?php

class Accident {
    public $id;
    public $occurredAt; // string
    public $location;   // string
    public $severity;   // string: 'minor','serious','severe','fatal'
    public $type;       // 'PDO'|'Injury'
    public $cost;       // float
    public $roadSegmentId;
    public $intersectionId;
    public $countermeasure;
}