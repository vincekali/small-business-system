<?php

require_once "../config/database.php";
require_once "../auth.php";

requireAdmin();

header("Content-Type: application/json");


/*
|--------------------------------------------------------------------------
| VALIDATE PERIOD
|--------------------------------------------------------------------------
*/

$period = $_GET["period"] ?? "7";

if (!in_array($period, ["7", "30", "month"], true)) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid period."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| DETERMINE DATE RANGE
|--------------------------------------------------------------------------
|
| Asia/Manila is explicitly used so the dashboard follows
| Philippine business time regardless of the server timezone.
|
*/

$today = new DateTime(
    "now",
    new DateTimeZone("Asia/Manila")
);


if ($period === "7") {

    // Today + previous 6 days
    $startDate =
        (clone $today)->modify("-6 days");

} elseif ($period === "30") {

    // Today + previous 29 days
    $startDate =
        (clone $today)->modify("-29 days");

} else {

    // First day of the current month
    $startDate =
        new DateTime(
            $today->format("Y-m-01"),
            new DateTimeZone("Asia/Manila")
        );
}


// End date is tomorrow.
// The SQL query uses < end date so today's entire
// business day is included.

$endDate =
    (clone $today)->modify("+1 day");


$start =
    $startDate->format("Y-m-d");

$end =
    $endDate->format("Y-m-d");


/*
|--------------------------------------------------------------------------
| GET DAILY SALES
|--------------------------------------------------------------------------
*/

$salesData = [];


$stmt = $conn->prepare("
    SELECT

        DATE(sale_date) AS sale_day,

        COALESCE(
            SUM(total_amount),
            0
        ) AS sales

    FROM sales

    WHERE sale_date >= ?
      AND sale_date < ?

    GROUP BY
        DATE(sale_date)

    ORDER BY
        sale_day ASC
");


if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to prepare sales query."
    ]);

    exit;
}


$stmt->bind_param(
    "ss",
    $start,
    $end
);


if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve sales data."
    ]);

    exit;
}


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $salesData[$row["sale_day"]] =
        (float) $row["sales"];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| GET DAILY PRODUCT COST
|--------------------------------------------------------------------------
|
| Uses the cost_price stored in sale_items.
| This preserves the actual product cost at the
| time the sale was made.
|
*/

$costData = [];


$stmt = $conn->prepare("
    SELECT

        DATE(s.sale_date) AS sale_day,

        COALESCE(
            SUM(
                si.cost_price *
                si.quantity
            ),
            0
        ) AS product_cost

    FROM sales s

    INNER JOIN sale_items si
        ON si.sale_id = s.id

    WHERE s.sale_date >= ?
      AND s.sale_date < ?

    GROUP BY
        DATE(s.sale_date)

    ORDER BY
        sale_day ASC
");


if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to prepare cost query."
    ]);

    exit;
}


$stmt->bind_param(
    "ss",
    $start,
    $end
);


if (!$stmt->execute()) {

    $stmt->close();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve product cost data."
    ]);

    exit;
}


$result =
    $stmt->get_result();


while ($row = $result->fetch_assoc()) {

    $costData[$row["sale_day"]] =
        (float) $row["product_cost"];
}


$stmt->close();


/*
|--------------------------------------------------------------------------
| BUILD COMPLETE DAILY CHART DATA
|--------------------------------------------------------------------------
|
| Days without sales are included as zero.
|
*/

$labels = [];

$sales = [];

$profit = [];


$currentDate =
    clone $startDate;


while ($currentDate < $endDate) {

    $date =
        $currentDate->format("Y-m-d");


    $dailySales =
        $salesData[$date] ?? 0;


    $dailyCost =
        $costData[$date] ?? 0;


    $dailyProfit =
        $dailySales - $dailyCost;


    $labels[] =
        $currentDate->format("M d");


    $sales[] =
        $dailySales;


    $profit[] =
        $dailyProfit;


    $currentDate->modify("+1 day");
}


/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([
    "success" => true,
    "labels" => $labels,
    "sales" => $sales,
    "profit" => $profit
]);

?>