<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/docs')]
class DocsController extends AbstractController
{
    #[Route('', name: 'api_docs', methods: ['GET'])]
    public function swaggerUi(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pafodex API Docs</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@3/swagger-ui.css" />
  <style>
    html {
      box-sizing: border-box;
      overflow: -moz-scrollbars-vertical;
      overflow-y: scroll;
    }
    *, *:before, *:after {
      box-sizing: inherit;
    }
    body {
      margin: 0;
      padding: 0;
    }
  </style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@3/swagger-ui-bundle.js" charset="UTF-8"></script>
  <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@3/swagger-ui-standalone-preset.js" charset="UTF-8"></script>
  <script>
    window.onload = function() {
      const ui = SwaggerUIBundle({
        url: '/api/docs/openapi.yaml',
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
          SwaggerUIBundle.presets.apis,
          SwaggerUIStandalonePreset
        ],
        plugins: [
          SwaggerUIBundle.plugins.DownloadUrl
        ],
        layout: 'BaseLayout'
      });
      window.ui = ui;
    };
  </script>
</body>
</html>
HTML;

        return new Response($html);
    }

    #[Route('/openapi.yaml', name: 'api_docs_openapi', methods: ['GET'])]
    public function openApiFile(): Response
    {
        $projectDir = dirname(__DIR__, 3);
        $filePath = $projectDir . '/openapi.yaml';

        if (!is_file($filePath)) {
            return new Response('openapi.yaml not found', Response::HTTP_NOT_FOUND);
        }

        return new Response(file_get_contents($filePath), Response::HTTP_OK, [
            'Content-Type' => 'application/yaml',
        ]);
    }
}
