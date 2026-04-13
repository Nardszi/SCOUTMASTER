<?php
// Sends a sample CSV file for batch scout registration import
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="sample_batch_registration.csv"');

$out = fopen('php://output', 'w');

// Header row
fputcsv($out, [
    'Surname Given Name M.I',
    'Registration Status (N=New / RR=Re-registering)',
    'Age',
    'Sex (Male/Female)',
    'Membership Card No.',
    'Highest Rank Earned',
    'Yrs in Scouting',
    'School',
    'Payment (Paid/Unpaid)'
]);

// Sample rows
$samples = [
    ['Dela Cruz, Juan A.',   'N',  12, 'Male',   'MC-2024-001', 'Tenderfoot',  1, 'Rizal Elementary School',   'Paid'],
    ['Santos, Maria B.',     'N',  11, 'Female', 'MC-2024-002', '',            0, 'Bonifacio Central School',  'Unpaid'],
    ['Reyes, Carlos M.',     'RR', 14, 'Male',   'MC-2023-045', 'Second Class', 3, 'Mabini High School',       'Paid'],
    ['Garcia, Ana L.',       'RR', 13, 'Female', 'MC-2023-078', 'First Class',  2, 'Aguinaldo Academy',        'Paid'],
    ['Lim, Jose P.',         'N',  10, 'Male',   'MC-2024-003', '',            0, 'Luna Elementary School',    'Unpaid'],
];

foreach ($samples as $row) {
    fputcsv($out, $row);
}

fclose($out);
exit;
