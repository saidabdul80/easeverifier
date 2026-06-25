NBAIS Result Checker Form Submission Documentation
Base URL
https://resultchecker.nbais.com.ng/
Form submit endpoint

The form submits with POST to:

https://resultchecker.nbais.com.ng/process-results-1.php

This comes from the form:

<form id="wrapped" action="process-results-1.php" method="POST">

The request is normal form-data / URL encoded form submission, not JSON.

Request method
POST
Content type

Use:

application/x-www-form-urlencoded

In Laravel:

Http::asForm()
Required payload fields
Field name	Description	Required	Example
website	Anti-spam honeypot field; should be empty	Yes	``
parent_cat	State ID	Yes	8
sub_cat	School / centre ID	Yes	depends on selected state
year	Exam year	Yes	2016
month-select	Exam month	Yes	June/July
exam_type	Exam type	Yes	SCIENCE
exam_no	Candidate exam number	Yes	1234567890
Form-data sample
website=
parent_cat=8
sub_cat=YOUR_SCHOOL_ID
year=2016
month-select=June/July
exam_type=SCIENCE
exam_no=YOUR_EXAM_NUMBER
Field explanation
website

This is a hidden anti-spam honeypot field:

<input id="website" name="website" type="text" value="">

Keep it empty.

website=
parent_cat

This is the state ID.

The field name is:

parent_cat

Example:

parent_cat=8

From the HTML, some state values include:

State	Value
Kwara	1
Benue	2
FCT	3
Osun	4
Oyo	5
Nassarawa	6
Kogi	7
Niger	8
Katsina	9
Jigawa	10
Kaduna	11
Kano	12
Adamawa	13
Plateau	14
Zamfara	15
Kebbi	16
Sokoto	17
Taraba	18
Yobe	19
Gombe	20
Bauchi	21
Borno	22
Enugu	23
Ogun	24
Lagos	25
Ondo	26
Ekiti	27
Abia	28
Akwa Ibom	29
Anambra	30
Bayelsa	31
Cross River	32
Delta	33
Ebonyi	34
Edo	35
Imo	36
Rivers	37
sub_cat

This is the school / centre field.

<select required name="sub_cat" id="sub_cat"></select>

In your provided HTML, the options are not loaded inside the page. They are likely loaded dynamically after selecting parent_cat.

So before submitting the result-check request, you need to find the AJAX endpoint that populates:

sub_cat

Likely flow:

Select state parent_cat
        ↓
JavaScript fetches schools/centres
        ↓
User selects school
        ↓
Selected school ID is sent as sub_cat

So the final result check cannot be completed with only the state name; you need the actual sub_cat value.

year

Exam year field:

year

Example:

year=2016

Available values in the HTML are from:

2008 - 2026
month-select

Exam month field:

month-select

Valid values:

June/July
Nov/Dec

Example:

month-select=June/July
exam_type

Exam type field:

exam_type

Valid values:

SAISSCE
SCIENCE
TAHFEEZ

Example:

exam_type=SCIENCE
exam_no

Candidate exam number field:

exam_no

Example:

exam_no=1234567890
PHP cURL example
<?php

$url = 'https://resultchecker.nbais.com.ng/process-results-1.php';

$payload = [
    'website'      => '',
    'parent_cat'   => '8',              // State ID, example: Niger
    'sub_cat'      => 'SCHOOL_ID_HERE', // School / centre ID
    'year'         => '2016',
    'month-select' => 'June/July',
    'exam_type'    => 'SCIENCE',
    'exam_no'      => 'YOUR_EXAM_NUMBER',
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,

    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
        'Origin: https://resultchecker.nbais.com.ng',
        'Referer: https://resultchecker.nbais.com.ng/',
        'User-Agent: Mozilla/5.0',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ],

    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT        => 60,
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($error) {
    throw new Exception("NBAIS request failed: {$error}");
}

if ($status < 200 || $status >= 400) {
    throw new Exception("NBAIS returned HTTP status {$status}");
}

echo $response;