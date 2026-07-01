<?php

namespace App\Services\Ai;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class OllamaClient
{
  public function __construct(
    private readonly Client $http = new Client(),
  ) {
  }

  /**
   * @return array<int, float>
   */
  public function embed(string $text): array
  {
    $baseUrl = rtrim((string) config('hsi_ai.ollama.base_url'), '/');
    $model = (string) config('hsi_ai.ollama.embed_model');
    $timeout = (int) config('hsi_ai.ollama.timeout', 120);

    try {
      $res = $this->http->post($baseUrl.'/api/embeddings', [
        'timeout' => $timeout,
        'json' => [
          'model' => $model,
          'prompt' => $text,
        ],
      ]);
    } catch (GuzzleException $e) {
      throw new \RuntimeException('Ollama embedding failed: '.$e->getMessage(), 0, $e);
    }

    $data = json_decode((string) $res->getBody(), true);
    $embedding = $data['embedding'] ?? null;
    if (! is_array($embedding) || $embedding === []) {
      throw new \RuntimeException('Ollama returned an empty embedding.');
    }

    return array_map('floatval', $embedding);
  }

  public function chat(string $systemPrompt, string $userPrompt): string
  {
    $baseUrl = rtrim((string) config('hsi_ai.ollama.base_url'), '/');
    $model = (string) config('hsi_ai.ollama.chat_model');
    $timeout = (int) config('hsi_ai.ollama.timeout', 120);

    try {
      $res = $this->http->post($baseUrl.'/api/chat', [
        'timeout' => $timeout,
        'json' => [
          'model' => $model,
          'stream' => false,
          'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
          ],
        ],
      ]);
    } catch (GuzzleException $e) {
      throw new \RuntimeException('Ollama chat failed: '.$e->getMessage(), 0, $e);
    }

    $data = json_decode((string) $res->getBody(), true);
    $content = $data['message']['content'] ?? null;
    if (! is_string($content) || trim($content) === '') {
      throw new \RuntimeException('Ollama returned an empty chat response.');
    }

    return trim($content);
  }
}
