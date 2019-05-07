<?php

require_once "../../vendor/autoload.php";
require_once "./QueryType.php";
require_once "./UserType.php";

try {
    $schema = new \GraphQL\Type\Schema([
        "query" => new QueryType()
    ]);

    $input_raw = file_get_contents("php://input");
    $input_raw = json_decode($input_raw, true);
    $query = $input_raw["query"];
    $variables = $input_raw["variables"] ?? [];

    $result = \GraphQL\GraphQL::executeQuery($schema, $query, null, null, $variables);
    $output = $result->toArray();
} catch (Throwable $exception) {
    $output = json_encode([
        "errors" => [
            "message" => $exception->getMessage(),
            "trace"   => $exception->getTrace()
        ]
    ]);
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($output);

