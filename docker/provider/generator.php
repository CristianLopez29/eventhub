<?php

declare(strict_types=1);

function generateDynamicXml(): string
{
    $events = [];
    $numBasePlans = random_int(2, 5);

    for ($i = 0; $i < $numBasePlans; ++$i) {
        $basePlanId = (string) random_int(100, 999);
        $title = generateTitle();
        $sellMode = random_int(1, 5) === 1 ? 'offline' : 'online';
        $numPlans = random_int(1, 3);

        $plans = [];
        for ($j = 0; $j < $numPlans; ++$j) {
            $plan = generatePlan($basePlanId);
            if ($plan !== null) {
                $plans[] = $plan;
            }
        }

        if ($plans !== []) {
            $events[] = [
                'base_plan_id' => $basePlanId,
                'title' => $title,
                'sell_mode' => $sellMode,
                'plans' => $plans,
            ];
        }
    }

    return buildXml($events);
}

function generateTitle(): string
{
    $titles = [
        'Camela en concierto',
        'Pantomima Full',
        'Los Morancos',
        'Tributo a Juanito Valderrama',
        'Concierto de Primavera',
        'Noche de Comedia',
        'Festival de Jazz',
        'Teatro Clasico',
        'Opera al Aire Libre',
        'Danza Contemporanea',
    ];

    return $titles[array_rand($titles)];
}

function generatePlan(string $basePlanId): ?array
{
    $startDate = generateDate();
    $endDate = generateEndDate($startDate);

    if ($startDate === null || $endDate === null) {
        return null;
    }

    $zones = [];
    $numZones = random_int(1, 4);

    for ($k = 0; $k < $numZones; ++$k) {
        $zone = generateZone();
        if ($zone !== null) {
            $zones[] = $zone;
        }
    }

    if ($zones === []) {
        return null;
    }

    return [
        'plan_id' => $basePlanId,
        'plan_start_date' => $startDate,
        'plan_end_date' => $endDate,
        'sell_from' => $startDate,
        'sell_to' => $endDate,
        'sold_out' => 'false',
        'zones' => $zones,
    ];
}

function generateDate(): ?string
{
    $year = random_int(2024, 2025);
    $month = random_int(1, 12);
    $day = random_int(1, 31);
    $hour = random_int(18, 22);
    $minute = random_int(0, 3) * 15;

    if (!checkdate($month, $day, $year)) {
        if (random_int(1, 3) === 1) {
            return sprintf('%d-%02d-%02dT%02d:%02d:00', $year, $month, $day, $hour, $minute);
        }

        return null;
    }

    return sprintf('%d-%02d-%02dT%02d:%02d:00', $year, $month, $day, $hour, $minute);
}

function generateEndDate(string $startDate): ?string
{
    try {
        $start = new DateTimeImmutable($startDate);
        $duration = random_int(1, 4) * 30;
        $end = $start->modify("+{$duration} minutes");

        return $end->format('Y-m-d\TH:i:s');
    } catch (\Exception) {
        return null;
    }
}

function generateZone(): ?array
{
    $names = ['Platea', 'Grada 1', 'Grada 2', 'VIP', 'General', 'Amfiteatre', 'Palco', 'A28', 'A42'];
    $name = $names[array_rand($names)];

    $price = generatePrice();
    if ($price === null) {
        return null;
    }

    $capacity = generateCapacity();
    if ($capacity === null) {
        return null;
    }

    return [
        'zone_id' => (string) random_int(1, 500),
        'capacity' => $capacity,
        'price' => $price,
        'name' => $name,
        'numbered' => random_int(0, 1) === 1 ? 'true' : 'false',
    ];
}

function generatePrice(): ?string
{
    $roll = random_int(1, 20);

    if ($roll === 1) {
        return 'invalid';
    }

    if ($roll === 2) {
        return '';
    }

    if ($roll === 3) {
        return '-10.00';
    }

    $price = random_int(10, 200);
    $cents = random_int(0, 99);

    return sprintf('%d.%02d', $price, $cents);
}

function generateCapacity(): ?string
{
    $roll = random_int(1, 20);

    if ($roll === 1) {
        return 'not_a_number';
    }

    if ($roll === 2) {
        return '-5';
    }

    return (string) random_int(0, 500);
}

function buildXml(array $events): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<planList xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.0" xsi:noNamespaceSchemaLocation="planList.xsd">' . "\n";
    $xml .= '   <output>' . "\n";

    foreach ($events as $event) {
        $xml .= sprintf(
            '      <base_plan base_plan_id="%s" sell_mode="%s" title="%s">' . "\n",
            htmlspecialchars($event['base_plan_id'], ENT_XML1, 'UTF-8'),
            htmlspecialchars($event['sell_mode'], ENT_XML1, 'UTF-8'),
            htmlspecialchars($event['title'], ENT_XML1, 'UTF-8')
        );

        foreach ($event['plans'] as $plan) {
            $xml .= sprintf(
                '         <plan plan_start_date="%s" plan_end_date="%s" plan_id="%s" sell_from="%s" sell_to="%s" sold_out="%s">' . "\n",
                $plan['plan_start_date'],
                $plan['plan_end_date'],
                $plan['plan_id'],
                $plan['sell_from'],
                $plan['sell_to'],
                $plan['sold_out']
            );

            foreach ($plan['zones'] as $zone) {
                $xml .= sprintf(
                    '            <zone zone_id="%s" capacity="%s" price="%s" name="%s" numbered="%s" />' . "\n",
                    $zone['zone_id'],
                    $zone['capacity'],
                    $zone['price'],
                    htmlspecialchars($zone['name'], ENT_XML1, 'UTF-8'),
                    $zone['numbered']
                );
            }

            $xml .= '         </plan>' . "\n";
        }

        $xml .= '      </base_plan>' . "\n";
    }

    $xml .= '   </output>' . "\n";
    $xml .= '</planList>';

    return $xml;
}
