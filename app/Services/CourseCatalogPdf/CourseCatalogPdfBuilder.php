<?php

namespace App\Services\CourseCatalogPdf;

use Dompdf\Dompdf;

class CourseCatalogPdfBuilder
{
    /**
     * @param array<int, array<string, mixed>> $rawCourses
     */
    public function buildGroupedLibraries(array $rawCourses): array
    {
        $keysToRemove = [
            'contentType',
            'url',
            'postDate',
            'courseInformation',
            'courseOutline',
            'courseRegulations',
            'courseLearningObjectives',
            'vendorName',
            'lessonModality',
            'subTopic',
            'courseCompletions',
            'courseImage',
            'objectID',
            '_highlightResult',
            '__position',
        ];

        $finalByLibrary = []; // library => topic => subTopic => [courses]

        foreach ($rawCourses as $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach ($keysToRemove as $key) {
                unset($item[$key]);
            }

            if (! isset($item['topic']) || (string) $item['topic'] === '') {
                continue;
            }

            if (isset($item['cldID'])) {
                $item['cldID'] = str_replace('CLD-', '', (string) $item['cldID']);
            }

            $item['courseLanguages'] = $this->normalizeLanguages($item['courseLanguages'] ?? null);

            if (! isset($item['singleSubTopic']) || (string) $item['singleSubTopic'] === '') {
                $item['singleSubTopic'] = 'Uncategorized';
            }

            if (isset($item['title'])) {
                $item['title'] = str_replace('&', 'and', (string) $item['title']);
            }

            $libraries = $this->extractLibraries($item);
            if (empty($libraries)) {
                $libraries = ['_No Library_'];
            }

            $topic = (string) $item['topic'];
            $subTopic = (string) ($item['singleSubTopic'] ?? 'Uncategorized');

            foreach ($libraries as $libraryName) {
                $finalByLibrary[$libraryName][$topic][$subTopic][] = $item;
            }
        }

        foreach ($finalByLibrary as &$topics) {
            ksort($topics, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($topics as &$subTopics) {
                ksort($subTopics, SORT_NATURAL | SORT_FLAG_CASE);
                foreach ($subTopics as &$items) {
                    usort($items, fn ($a, $b) => strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? '')));
                }
            }
        }

        $librariesList = [];
        foreach ($finalByLibrary as $libraryName => $topics) {
            $topicList = [];
            foreach ($topics as $topicName => $subTopics) {
                $subTopicList = [];
                foreach ($subTopics as $subTopicName => $items) {
                    $subTopicList[] = [
                        'subTopic' => $subTopicName,
                        'subCourses' => $items,
                    ];
                }
                $topicList[] = [
                    'topic' => $topicName,
                    'subTopics' => $subTopicList,
                ];
            }
            $librariesList[] = [
                'library' => $libraryName,
                'topics' => $topicList,
            ];
        }

        return $librariesList;
    }

    public function buildHtml(array $librariesList): string
    {
        $courseTableHtml = $this->getCoursesTable($librariesList);

        $body = '<div style="text-align: center">
    <img src="https://hsiassetstorage.sfo2.digitaloceanspaces.com/assets/images/general/courses-catalog-main-image.png" alt="" width="768" height="434" />
</div>
<div style="text-align: center">&nbsp;</div>
<div style="text-align: left">
    <img src="https://hsiassetstorage.sfo2.digitaloceanspaces.com/assets/images/hsi-company-logos/HSI_Logo_Color_RGB_300x300.png" alt="" width="84" height="84" />
</div>
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 70pt; color: #387bb6; font-weight: bold;">
    Course Catalog
</div>
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 22pt; color: #000000;">
    One Partner. <strong>Multiple Workplace Solutions.</strong>
</div>
<div class="apitemplate-page-break" style="page-break-after: always"></div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; font-size: 30pt; color: #387bb6; font-weight: bold;">
    The HSI Difference
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-size: 18pt; font-family: Arial, Helvetica, sans-serif; color: #5a1128;">
    Award-Winning Training
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; line-height: 1.5">
    The HSI course library contains over 5,000 of the most-effective Safety,
    Skills, and HR training titles available. Our full-length courses average
    15-55 minutes in length while our microlearning courses average 4-10 minutes.
    All of our rich, interactive courses ensure a front-of-the-seat learning
    experience, and come with the HSI LMS, or can be used in your own LMS.
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; font-size: 18pt; color: #5a1128;">Engaging Content</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; line-height: 1.5">
    HSI builds courseware with production values that are second to none:
    vibrant, 3D animations, full narration, and reviewed by subject matter
    experts, so you know the content your employees receive can be trusted.
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; color: #5a1128; font-family: Arial, Helvetica, sans-serif; font-size: 18pt;">
    Always Current, Always Compliant
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; line-height: 1.5">
    HSI continuously updates our titles with new content, fresh imagery and
    improved features to ensure our courses remain accurate, relevant and
    engaging. HSI courses are also always compliant with updated OSHA, HR, and
    international regulations.
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; font-size: 18pt; color: #5a1128;">
    AN EASY-TO-USE LEARNING MANAGEMENT SYSTEM
</div>
<div style="padding-top:10px; margin-block-start: 10px; margin-block-end: 10px; font-family: Arial, Helvetica, sans-serif; line-height: 1.5">
    The HSI LMS has an intuitive interface which allows employees to easily find
    and complete required training or identify elective courses they want to take.
    Administrator access makes assigning, tracking, and reporting on training
    programs simple. Back-end administrator access allows for customized training
    plans, out-of-the-box compliance reports, and the ability to load and assign
    your own training content.
     <img src="https://hsiassetstorage.sfo2.digitaloceanspaces.com/assets/images/general/lms-items.png" alt="lm-items" width="100%" height="auto" />
</div><div class="apitemplate-page-break" style="page-break-after: always"></div>'.$courseTableHtml;

        return '<html><head>'.$this->getTemplateStyles().'</head><body>'.$body.'</body></html>';
    }

    /**
     * Render PDF bytes from HTML using Dompdf.
     */
    public function renderPdfBinary(string $html): string
    {
        $dompdf = new Dompdf();
        $options = $dompdf->getOptions();
        $options->set(['isRemoteEnabled' => true]);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    private function getCoursesTable(array $coursesArray): string
    {
        $finalHtml = '';

        foreach ($coursesArray as $library) {
            foreach (($library['topics'] ?? []) as $topic) {
                $finalHtml .= '<div style="background-color: #003f6f; width: 100%; padding: 10px 0; text-align: left;" class="main-category">'
                    .'<h2 style="color: white; font-size: 14pt; font-weight: bold; margin: 0; text-align: left; padding-left: 10px;">'
                    .htmlspecialchars((string) ($topic['topic'] ?? ''))
                    .'</h2></div>';

                foreach (($topic['subTopics'] ?? []) as $sub) {
                    $thStyle = 'background-color: #5fc2ff; font-size: 10pt; font-weight: bold; color: black; padding: 8px; text-align: left;';
                    $finalHtml .= '<table style="width: 100%; border-collapse: collapse;" class="sub-topic-table">
                    <thead>
                        <tr>
                            <th style="'.$thStyle.' width: 70%;">'.htmlspecialchars((string) ($sub['subTopic'] ?? '')).'</th>
                            <th style="'.$thStyle.' width: 10%;">ID</th>
                            <th style="'.$thStyle.' width: 10%;">Time</th>
                            <th style="'.$thStyle.' width: 10%;">Languages</th>
                        </tr>
                    </thead>
                </table>';

                    $subCourses = $sub['subCourses'] ?? [];
                    if (! empty($subCourses) && is_array($subCourses)) {
                        $finalHtml .= '<table style="width: 100%; border-collapse: collapse;" class="inner-table">';
                        foreach ($subCourses as $course) {
                            if (! is_array($course)) {
                                continue;
                            }
                            $tdStyle = 'background-color: white; font-size: 9pt; color: black; font-weight: normal; padding: 8px; text-align: left;';
                            $finalHtml .= '<tr>
                            <td style="'.$tdStyle.' width: 70%;">'.htmlspecialchars((string) ($course['title'] ?? '')).'</td>
                            <td style="'.$tdStyle.' width: 10%;">'.htmlspecialchars((string) ($course['cldID'] ?? '')).'</td>
                            <td style="'.$tdStyle.' width: 10%;">'.htmlspecialchars((string) ($course['courseLength'] ?? '')).'</td>
                            <td style="'.$tdStyle.' width: 10%;">'.htmlspecialchars((string) ($course['courseLanguages'] ?? '')).'</td>
                        </tr>';
                        }
                        $finalHtml .= '</table>';
                    }
                }
            }
        }

        return $finalHtml;
    }

    private function getTemplateStyles(): string
    {
        return '<style>
         body {
  -webkit-font-smoothing: antialiased;
  background: #ffffff;
  font-family: "Arial" !important;
}

p {
    margin-block-start: 10px !important;
    margin-block-end: 10px !important;
}

hr {
  border: 1px solid #c2e0f4;
}

.main-category {
  background-color: #003f6f;
  width: 100%;
  padding: 10px 0px;
  text-align: left;
  font-family: "Arial", sans-serif;
}

.main-category h2 {
  color: white;
  font-size: 14pt;
  font-weight: bold;
  margin: 0;
  text-align: left;
  font-family: "Arial", sans-serif;
  padding-left: 10px;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-family: "Arial", sans-serif;
}

th {
  background-color: #5fc2ff;
  font-size: 10pt;
  font-weight: bold;
  color: black;
  padding: 8px;
  font-family: "Arial", sans-serif;
  text-align: left;
}

td {
  background-color: white;
  font-size: 8pt;
  color: black;
  font-weight: normal;
  font-family: "Arial", sans-serif;
  padding: 8px;
  text-align: left;
}

.page-container .page::after {
  content: counter(page);
}

.page-container .pages::after {
  content: counter(pages);
}

.page-container {
  position: running(pageContainer);
}

@page {
  @bottom-right {
    content: element(pageContainer);
  }
}

.new-page {
  page-break-before: always;
}
</style>';
    }

    private function normalizeLanguages(mixed $value): string
    {
        if (is_array($value)) {
            $langs = [];
            foreach ($value as $lang) {
                $code = $this->getLanguageCode((string) $lang);
                if ($code) {
                    $langs[] = $code;
                }
            }

            return strtoupper(implode(', ', array_values(array_unique($langs))));
        }

        if (is_string($value) && $value !== '') {
            $langs = array_filter(array_map('trim', explode(',', $value)));

            return strtoupper(implode(', ', array_values(array_unique($langs))));
        }

        return '';
    }

    private function getLanguageCode(string $languageName): ?string
    {
        $languageMap = [
            'English' => 'en',
            'Spanish' => 'es',
            'French' => 'fr',
            'German' => 'de',
            'Italian' => 'it',
            'Portuguese' => 'pt',
            'Dutch' => 'nl',
            'Chinese' => 'zh',
            'Japanese' => 'ja',
            'Korean' => 'ko',
            'Russian' => 'ru',
            'Arabic' => 'ar',
            'Hindi' => 'hi',
            'Bengali' => 'bn',
            'Turkish' => 'tr',
            'Vietnamese' => 'vi',
            'Polish' => 'pl',
            'Persian' => 'fa',
            'Urdu' => 'ur',
        ];

        $normalized = ucfirst(strtolower(trim($languageName)));

        return $languageMap[$normalized] ?? null;
    }

    /**
     * @return array<int, string>
     */
    private function extractLibraries(array $item): array
    {
        if (! isset($item['collections']) || empty($item['collections'])) {
            return [];
        }

        $names = [];
        foreach ((array) $item['collections'] as $c) {
            if (is_array($c) && isset($c['name'])) {
                $names[] = (string) $c['name'];
            } elseif (is_string($c)) {
                $names[] = $c;
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $names))));
    }
}

