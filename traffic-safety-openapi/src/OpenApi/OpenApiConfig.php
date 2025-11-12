<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         version="1.0.0",
 *         title="Traffic Safety Platform API",
 *         description="API documentation for the Traffic Safety decision management system."
 *     ),
 *     @OA\Server(
 *         url="/",
 *         description="Relative base URL"
 *     )
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     @OA\Property(property="count", type="integer", format="int32"),
 *     @OA\Property(property="status", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="MonetaryAmount",
 *     required={"amount"},
 *     @OA\Property(property="amount", type="number", format="double"),
 *     @OA\Property(property="currency", type="string", example="USD")
 * )
 *
 * @OA\Schema(
 *     schema="TimePeriod",
 *     required={"startDate","endDate"},
 *     @OA\Property(property="startDate", type="string", format="date-time"),
 *     @OA\Property(property="endDate", type="string", format="date-time"),
 *     @OA\Property(property="durationDays", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="AccidentLocation",
 *     required={"locationType","locationId"},
 *     @OA\Property(property="locationType", type="string", example="road_segment"),
 *     @OA\Property(property="locationId", type="integer", format="int64"),
 *     @OA\Property(property="latitude", type="number", format="float", nullable=true),
 *     @OA\Property(property="longitude", type="number", format="float", nullable=true),
 *     @OA\Property(property="distanceFromStart", type="number", format="float", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="Accident",
 *     required={"id","occurredAt","location","type"},
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="occurredAt", type="string", format="date-time"),
 *     @OA\Property(property="location", ref="#/components/schemas/AccidentLocation"),
 *     @OA\Property(property="cost", ref="#/components/schemas/MonetaryAmount", nullable=true),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="severity", type="string", nullable=true),
 *     @OA\Property(property="collisionType", type="string", nullable=true),
 *     @OA\Property(property="causeFactor", type="string", nullable=true),
 *     @OA\Property(property="weatherCondition", type="string", nullable=true),
 *     @OA\Property(property="roadCondition", type="string", nullable=true),
 *     @OA\Property(property="visibilityCondition", type="string", nullable=true),
 *     @OA\Property(property="injuredPersonsCount", type="integer", nullable=true),
 *     @OA\Property(property="locationDescription", type="string", nullable=true),
 *     @OA\Property(property="requiresImmediateAttention", type="boolean"),
 *     @OA\Property(property="daysSinceOccurrence", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="User",
 *     required={"id","email","firstName","lastName","role","isActive"},
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="firstName", type="string"),
 *     @OA\Property(property="lastName", type="string"),
 *     @OA\Property(property="displayName", type="string"),
 *     @OA\Property(property="role", type="string", example="analyst"),
 *     @OA\Property(property="isActive", type="boolean"),
 *     @OA\Property(property="createdAt", type="string", format="date-time"),
 *     @OA\Property(property="updatedAt", type="string", format="date-time"),
 *     @OA\Property(property="lastLoginAt", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="Project",
 *     required={"id","countermeasureId","hotspotId","period","expectedCost","actualCost","status"},
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="countermeasureId", type="integer", format="int64"),
 *     @OA\Property(property="hotspotId", type="integer", format="int64"),
 *     @OA\Property(property="period", ref="#/components/schemas/TimePeriod"),
 *     @OA\Property(property="expectedCost", ref="#/components/schemas/MonetaryAmount"),
 *     @OA\Property(property="actualCost", ref="#/components/schemas/MonetaryAmount"),
 *     @OA\Property(property="status", type="string")
 * )
 *
 * @OA\Schema(
 *     schema="ObservedCrashes",
 *     type="object",
 *     additionalProperties=@OA\Schema(type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="RoadSegment",
 *     required={"id","lengthKm","laneCount","functionalClass","speedLimitKmh","aadt"},
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="code", type="string", nullable=true),
 *     @OA\Property(property="lengthKm", type="number", format="float"),
 *     @OA\Property(property="laneCount", type="integer"),
 *     @OA\Property(property="functionalClass", type="string"),
 *     @OA\Property(property="speedLimitKmh", type="integer"),
 *     @OA\Property(property="aadt", type="integer"),
 *     @OA\Property(
 *         property="geoLocation",
 *         type="object",
 *         @OA\Property(property="wkt", type="string"),
 *         @OA\Property(property="city", type="string", nullable=true),
 *         @OA\Property(property="street", type="string", nullable=true)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="Intersection",
 *     required={"id","controlType","numberOfLegs","hasCameras","aadt"},
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="code", type="string", nullable=true),
 *     @OA\Property(property="controlType", type="string"),
 *     @OA\Property(property="numberOfLegs", type="integer"),
 *     @OA\Property(property="hasCameras", type="boolean"),
 *     @OA\Property(property="aadt", type="integer"),
 *     @OA\Property(property="spfModelReference", type="string"),
 *     @OA\Property(
 *         property="geoLocation",
 *         type="object",
 *         @OA\Property(property="wkt", type="string"),
 *         @OA\Property(property="city", type="string", nullable=true),
 *         @OA\Property(property="street", type="string", nullable=true)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="Hotspot",
 *     required={"id","location","period","expectedCrashes","riskScore","status"},
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(
 *         property="location",
 *         type="object",
 *         required={"type","data"},
 *         @OA\Property(property="type", type="string", example="road_segment"),
 *         @OA\Property(property="data", type="object")
 *     ),
 *     @OA\Property(property="period", ref="#/components/schemas/TimePeriod"),
 *     @OA\Property(property="observedCrashes", ref="#/components/schemas/ObservedCrashes", nullable=true),
 *     @OA\Property(property="expectedCrashes", type="number", format="float"),
 *     @OA\Property(property="riskScore", type="number", format="float"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="screeningParameters", type="object", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="Countermeasure",
 *     required={"id","name","targetType","cmf","lifecycleStatus"},
 *     @OA\Property(property="id", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="targetType", type="string"),
 *     @OA\Property(
 *         property="affectedCollisionTypes",
 *         type="array",
 *         @OA\Items(type="string")
 *     ),
 *     @OA\Property(
 *         property="affectedSeverities",
 *         type="array",
 *         @OA\Items(type="string")
 *     ),
 *     @OA\Property(property="cmf", type="number", format="float"),
 *     @OA\Property(property="lifecycleStatus", type="string"),
 *     @OA\Property(property="implementationCost", ref="#/components/schemas/MonetaryAmount"),
 *     @OA\Property(property="expectedAnnualSavings", type="number", format="float", nullable=true),
 *     @OA\Property(property="evidence", type="string", nullable=true)
 * )
 */
final class OpenApiConfig
{
}

