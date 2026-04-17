<?php
// header('Access-Control-Allow-Origin: *');
// Allow from any origin
if (isset($_SERVER["HTTP_ORIGIN"])) {
    // You can decide if the origin in $_SERVER['HTTP_ORIGIN'] is something you want to allow, or as we do here, just allow all
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
} else {
    //No HTTP_ORIGIN set, so we allow any. You can disallow if needed here
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 600");    // cache for 10 minutes

if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
    if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_METHOD"]))
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT"); //Make sure you remove those you do not want to support

    if (isset($_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"]))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    //Just exit with 200 OK with the above headers for OPTIONS method
    exit(0);
}
//From here, handle the request as it is ok
error_reporting(E_ALL);
ini_set('display_errors', 'On');
// include(dirname( dirname(__FILE__) ).'/vendor/autoload.php');
require(dirname(dirname(__FILE__)) . '/classes/generic.class.php');

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfReport extends Generic
{
    function __construct() {}

    public function createPdfFromHtmlFile($url, $filename)
    {
        $websiteContent = file_get_contents($url);

        $dompdf = new Dompdf();
        $options = $dompdf->getOptions();
        $options->set(array('isRemoteEnabled' => true));
        $dompdf->loadHtml($websiteContent);

        $dompdf->render();
        // $dompdf->stream();
        $output = $dompdf->output();
        $target_dir = dirname(dirname(__FILE__)) . "/reports/pdf/";
        file_put_contents($target_dir . $filename . '.pdf', $output);
    }

    public function createPDFManual($url, $filename)
    {
        $websiteContent = file_get_contents($url);

        $dompdf = new Dompdf();
        $options = $dompdf->getOptions();
        $options->set(array('isRemoteEnabled' => true));
        $dompdf->loadHtml($websiteContent);

        $dompdf->render();
        // $dompdf->stream();
        $output = $dompdf->output();
        $target_dir = dirname(dirname(__FILE__)) . "/reports/pdf/";
        file_put_contents($target_dir . $filename . '.pdf', $output);
    }

    public function createHtmlPage()
    {

        $request_body = file_get_contents('php://input');
        $data = json_decode($request_body, true);
        $title = $data['title'];
        $name = str_replace(" ", "-", $data['name']);
        $reportHtml = $data['reportHtml'];

        $target_dir = dirname(dirname(__FILE__)) . "/reports/";
        $htmlContent = $this->htmlTemplate($title, $name, $reportHtml);
        $filename = "HRCA-" . $name . "-" . date("Y-m-d:h:i:s");
        $myFile = $target_dir . $filename . ".html"; // or .php
        $fh = fopen($myFile, 'w'); // or die("error");
        fwrite($fh, $htmlContent);
        fclose($fh);
        $url = 'https://apismd.hsi.com/reports/' . $filename . '.html';
        $this->createPdfFromHtmlFile($url, $filename);
        $pdfLocation = dirname(dirname(__FILE__)) . '/reports/pdf/' . $filename . '.pdf';
        $cdnUrl = parent::uploadReportPdfToDigitalOcean($pdfLocation, $filename . '.pdf');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['pdfUrl' => $cdnUrl]);
    }

    public function htmlTemplate($title, $name, $reportHtml)
    {
        //$title = 'Safety Training Needs Assessment';
        $htmlContent = ' <html lang="en" style="--sw-progress-width: 100%;">

        <head>

            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
            <meta name="referrer" content="strict-origin-when-cross-origin">
            <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:ital,wght@0,200;0,300;0,400;0,500;0,600;0,700;1,200;1,300;1,400;1,500;1,600;1,700&amp;display=swap" rel="stylesheet">
            <title>
                HSI | ' . $title . '
</title>

<link rel="stylesheet" type="text/css" media="screen" href="https://apismd.hsi.com/reports/assets/styles-hrca.css" />
<link rel="stylesheet" type="text/css" media="screen" href="https://apismd.hsi.com/reports/assets/styles-hrca-appended.css" />
</head>

<body class="survey-entry survey-t4c form-loaded">
    <div class="logo">
        <img src="https://apismd.hsi.com/reports/assets/hsi-logo.png">
    </div>
    <div class="survey-title">
        ' . $title . '
    </div>
    <div class="survey-wrapper">

        <div id="smartwizard" dir="" class="sw sw-theme-basic sw-justified">


            <div class="tab-content">

                <div id="step-21" data-page-num="21" class="tab-pane report-pane" role="tabpanel"
                    aria-labelledby="step-21" style="">

                    ' . $reportHtml . '

                </div>
            </div>

        </div>

    </div>

</body>

</html>';
        $htmlContent = str_replace(
            'https://hsiassetstorage.sfo2.digitaloceanspaces.com/assets/images/solutions/homeIcons/safety-data-sheets-icon.svg',
            'https://apismd.hsi.com/reports/assets/safety-data-sheets-icon.jpg',
            $htmlContent
        );
        return $htmlContent;
    }
}
$pdfReport = new PdfReport();
