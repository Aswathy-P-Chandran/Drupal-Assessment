<?php

declare(strict_types=1);

namespace Drupal\dataset_publisher;

use Psr\Http\Client\ClientInterface;

/**
 * @todo Add class description.
 */
final class OpenAiService {
  protected ClientInterface $httpClient;
  protected string $apiKey;

  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
    $this->apiKey = 'AIzaSyAvUEsrmnTevx48y0v5xXB6N0f5XXyiARA';
  }


  public function generateMetadata(array $rows, string $language = 'en'): array {
    // Convert sample array to JSON for the Gemini prompt
    $jsonSample = json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $prompt = "You are a data analyst. Analyze the following dataset sample and provide the analysis in JSON format with keys: " .
              "\"title\", \"description\", \"tags\" (as an array of strings), and \"category\". " .
              "Dataset is $jsonSample";

    $response = $this->httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $this->apiKey, [
      'headers' => ['Content-Type' => 'application/json'],
      'json' => [
        'contents' => [
          [
            'parts' => [
              ['text' => $prompt],
            ],
          ],
        ],
      ],
    ]);

    $body = json_decode($response->getBody()->getContents(), TRUE);
    $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';

    // Try to decode the JSON directly from Gemini response
    $parsed = $this->_parseOutput($text);
    return $parsed;
  }

  private function _parseOutput(string $text): array {
    // Remove any triple quotes and code block markers (```json ... ```)
    $text = trim($text);
  
    // Remove triple quotes or backticks
    $text = preg_replace('/^```json|```|"""|```/m', '', $text);
    $text = trim($text);
  
    // Decode cleaned JSON
    $parsed = json_decode($text, TRUE);
  
    if (!is_array($parsed)) {
      \Drupal::logger('ai_dataset_publisher')->warning('Invalid JSON returned by Gemini: @text', ['@text' => $text]);
      return [];
    }
  
    return [
      'title' => $parsed['title'] ?? '',
      'description' => $parsed['description'] ?? '',
      'tags' => $parsed['tags'] ?? [],
      'category' => $parsed['category'] ?? '',
    ];
  }

  private function parseOutput(string $text): array {
    $lines = explode("\n", $text);
    $output = [
      'title' => '',
      'description' => '',
      'tags' => [],
      'category' => '',
    ];

    foreach ($lines as $line) {
      if (stripos($line, 'title:') !== false) {
        $output['title'] = trim(str_replace('Title:', '', $line));
      } elseif (stripos($line, 'description:') !== false) {
        $output['description'] = trim(str_replace('Description:', '', $line));
      } elseif (stripos($line, 'tags:') !== false) {
        $output['tags'] = array_map('trim', explode(',', str_replace('Tags:', '', $line)));
      } elseif (stripos($line, 'category:') !== false) {
        $output['category'] = trim(str_replace('Category:', '', $line));
      }
    }

    return $output;
  }
}