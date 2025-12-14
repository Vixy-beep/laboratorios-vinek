<?php
/**
 * JORISE SECURITY ENGINE - PHP Edition
 * Motor de análisis de seguridad avanzado con IA
 * Réplica completa de funcionalidades Python
 */

require_once 'config.php';

class JoriseEngine {
    
    // API Keys (agregar a config.php)
    private $virustotalKey;
    private $geminiKey;
    private $alienvaultKey;
    private $safeBrowsingKey;
    
    public function __construct() {
        $this->virustotalKey = defined('VIRUSTOTAL_API_KEY') ? VIRUSTOTAL_API_KEY : '';
        $this->geminiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
        $this->alienvaultKey = defined('ALIENVAULT_API_KEY') ? ALIENVAULT_API_KEY : '';
        $this->safeBrowsingKey = defined('GOOGLE_SAFE_BROWSING_KEY') ? GOOGLE_SAFE_BROWSING_KEY : '';
    }
    
    /**
     * ANÁLISIS COMPLETO DE ARCHIVO
     */
    public function analyzeFile($filePath, $fileName) {
        $analysis = [
            'file_name' => $fileName,
            'file_size' => filesize($filePath),
            'timestamp' => date('Y-m-d H:i:s'),
            'analysis_type' => 'file',
            'threat_score' => 0,
            'verdict' => 'clean',
            'indicators' => [],
            'details' => []
        ];
        
        // 1. Calcular hashes
        $hashes = $this->calculateHashes($filePath);
        $analysis['hashes'] = $hashes;
        
        // 2. Análisis estático
        $staticAnalysis = $this->staticFileAnalysis($filePath, $fileName);
        $analysis['threat_score'] += $staticAnalysis['threat_score'];
        $analysis['indicators'] = array_merge($analysis['indicators'], $staticAnalysis['indicators']);
        
        // 3. VirusTotal
        if ($this->virustotalKey) {
            $vtResult = $this->checkVirusTotal($hashes['sha256'], 'file');
            if ($vtResult) {
                $analysis['virustotal'] = $vtResult;
                $analysis['details']['vt_detections'] = $vtResult['malicious'];
                $analysis['details']['vt_total'] = $vtResult['total'];
                
                // Si VirusTotal detecta amenaza, aumentar score
                if ($vtResult['malicious'] > 0) {
                    $detection_rate = $vtResult['malicious'] / max($vtResult['total'], 1);
                    $analysis['threat_score'] += $detection_rate * 50;
                    $analysis['indicators'][] = "VirusTotal: {$vtResult['malicious']}/{$vtResult['total']} motores detectaron amenaza";
                }
            }
        }
        
        // 4. Buscar strings sospechosos
        $suspiciousStrings = $this->findSuspiciousStrings($filePath);
        if (!empty($suspiciousStrings)) {
            $analysis['threat_score'] += count($suspiciousStrings) * 5;
            $analysis['details']['suspicious_strings'] = $suspiciousStrings;
            foreach ($suspiciousStrings as $str) {
                $analysis['indicators'][] = "String sospechoso: $str";
            }
        }
        
        // 5. Análisis de comportamiento potencial
        $behavioralIndicators = $this->analyzeBehavioralIndicators($filePath);
        $analysis['details']['behavioral'] = $behavioralIndicators;
        $analysis['threat_score'] += $behavioralIndicators['score'];
        $analysis['indicators'] = array_merge($analysis['indicators'], $behavioralIndicators['indicators']);
        
        // 6. Veredicto final
        $analysis['threat_score'] = min($analysis['threat_score'], 100);
        
        if ($analysis['threat_score'] >= 70) {
            $analysis['verdict'] = 'malicious';
            $analysis['threat_level'] = 'critical';
        } elseif ($analysis['threat_score'] >= 40) {
            $analysis['verdict'] = 'suspicious';
            $analysis['threat_level'] = 'high';
        } elseif ($analysis['threat_score'] >= 20) {
            $analysis['verdict'] = 'potentially_unwanted';
            $analysis['threat_level'] = 'medium';
        } else {
            $analysis['verdict'] = 'clean';
            $analysis['threat_level'] = 'low';
        }
        
        // 7. Recomendaciones
        $analysis['recommendations'] = $this->generateRecommendations($analysis);
        
        // 8. Análisis con IA (Gemini)
        if ($this->geminiKey) {
            $analysis['ai_report'] = $this->generateAIReport($analysis);
        }
        
        return $analysis;
    }
    
    /**
     * ANÁLISIS COMPLETO DE URL
     */
    public function analyzeURL($url) {
        $analysis = [
            'url' => $url,
            'target' => $url,
            'timestamp' => date('Y-m-d H:i:s'),
            'analysis_type' => 'url',
            'threat_score' => 0,
            'verdict' => 'clean',
            'indicators' => [],
            'details' => [],
            'warnings' => []
        ];
        
        // 1. Validar URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception('URL inválida. Formato correcto: https://ejemplo.com');
        }
        
        // 2. Verificar conectividad básica (timeout 5 segundos)
        $urlParts = parse_url($url);
        $host = $urlParts['host'] ?? '';
        
        if (!$host) {
            throw new Exception('No se pudo extraer el dominio de la URL');
        }
        
        // Verificar DNS
        $dnsCheck = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (empty($dnsCheck)) {
            $analysis['warnings'][] = 'El dominio no resuelve DNS. Podría estar caído o ser inválido.';
            $analysis['threat_score'] += 5;
        }
        
        // 2. Análisis de dominio
        $domainAnalysis = $this->analyzeDomain($url);
        $analysis['threat_score'] += $domainAnalysis['threat_score'];
        $analysis['indicators'] = array_merge($analysis['indicators'], $domainAnalysis['indicators']);
        $analysis['details']['domain'] = $domainAnalysis;
        
        // 3. Google Safe Browsing
        if ($this->safeBrowsingKey) {
            $safeBrowsing = $this->checkSafeBrowsing($url);
            if ($safeBrowsing && $safeBrowsing['threat_detected']) {
                $analysis['threat_score'] += 80;
                $analysis['indicators'][] = "Google Safe Browsing: {$safeBrowsing['threat_type']}";
                $analysis['details']['safe_browsing'] = $safeBrowsing;
            }
        }
        
        // 4. VirusTotal URL scan
        if ($this->virustotalKey) {
            $urlHash = base64_encode($url);
            $vtResult = $this->checkVirusTotal($urlHash, 'url', $url);
            if ($vtResult) {
                $analysis['virustotal'] = $vtResult;
                if ($vtResult['malicious'] > 0) {
                    $analysis['threat_score'] += ($vtResult['malicious'] / max($vtResult['total'], 1)) * 50;
                    $analysis['indicators'][] = "VirusTotal URL: {$vtResult['malicious']}/{$vtResult['total']} detectaron amenaza";
                }
            }
        }
        
        // 5. AlienVault OTX Threat Intelligence
        if ($this->alienvaultKey) {
            $otxResult = $this->checkAlienVault($url);
            if ($otxResult && $otxResult['pulse_count'] > 0) {
                $analysis['threat_score'] += min($otxResult['pulse_count'] * 10, 40);
                $analysis['indicators'][] = "AlienVault OTX: {$otxResult['pulse_count']} pulsos de amenaza";
                $analysis['details']['alienvault'] = $otxResult;
            }
        }
        
        // 6. Verificar SSL/HTTPS
        $sslCheck = $this->checkSSL($url);
        $analysis['details']['ssl'] = $sslCheck;
        if (!$sslCheck['valid']) {
            $analysis['threat_score'] += 10;
            $analysis['indicators'][] = $sslCheck['message'];
        }
        
        // 7. Análisis de phishing
        $phishingCheck = $this->detectPhishing($url);
        $analysis['threat_score'] += $phishingCheck['score'];
        $analysis['indicators'] = array_merge($analysis['indicators'], $phishingCheck['indicators']);
        $analysis['details']['phishing'] = $phishingCheck;
        
        // 8. Veredicto final
        $analysis['threat_score'] = min($analysis['threat_score'], 100);
        
        if ($analysis['threat_score'] >= 70) {
            $analysis['verdict'] = 'malicious';
            $analysis['threat_level'] = 'critical';
        } elseif ($analysis['threat_score'] >= 40) {
            $analysis['verdict'] = 'suspicious';
            $analysis['threat_level'] = 'high';
        } elseif ($analysis['threat_score'] >= 20) {
            $analysis['verdict'] = 'potentially_unwanted';
            $analysis['threat_level'] = 'medium';
        } else {
            $analysis['verdict'] = 'clean';
            $analysis['threat_level'] = 'low';
        }
        
        $analysis['recommendations'] = $this->generateRecommendations($analysis);
        
        // 9. Reporte con IA
        if ($this->geminiKey) {
            $analysis['ai_report'] = $this->generateAIReport($analysis);
        }
        
        return $analysis;
    }
    
    /**
     * CALCULAR HASHES DE ARCHIVO
     */
    private function calculateHashes($filePath) {
        return [
            'md5' => md5_file($filePath),
            'sha1' => sha1_file($filePath),
            'sha256' => hash_file('sha256', $filePath)
        ];
    }
    
    /**
     * ANÁLISIS ESTÁTICO DE ARCHIVO
     */
    private function staticFileAnalysis($filePath, $fileName) {
        $threatScore = 0;
        $indicators = [];
        
        $fileSize = filesize($filePath);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // 1. Extensiones peligrosas
        $dangerousExts = ['exe', 'dll', 'scr', 'bat', 'cmd', 'ps1', 'vbs', 'js', 'jar', 'app', 'deb', 'rpm'];
        if (in_array($ext, $dangerousExts)) {
            $threatScore += 15;
            $indicators[] = "Extensión potencialmente peligrosa: .$ext";
        }
        
        // 2. Tamaño sospechoso
        if ($fileSize < 1000) {
            $threatScore += 5;
            $indicators[] = "Tamaño sospechosamente pequeño: " . number_format($fileSize) . " bytes";
        } elseif ($fileSize > 100 * 1024 * 1024) { // 100MB
            $threatScore += 5;
            $indicators[] = "Archivo muy grande: " . number_format($fileSize / 1024 / 1024, 2) . " MB";
        }
        
        // 3. Magic numbers (primeros bytes)
        $handle = fopen($filePath, 'rb');
        $header = fread($handle, 4);
        fclose($handle);
        
        // PE executable (Windows)
        if (substr($header, 0, 2) === 'MZ') {
            $threatScore += 10;
            $indicators[] = "Ejecutable PE de Windows detectado";
        }
        
        // ELF executable (Linux)
        if ($header === "\x7fELF") {
            $threatScore += 10;
            $indicators[] = "Ejecutable ELF de Linux detectado";
        }
        
        // Mach-O (macOS)
        if (in_array($header, ["\xFE\xED\xFA\xCE", "\xFE\xED\xFA\xCF", "\xCE\xFA\xED\xFE", "\xCF\xFA\xED\xFE"])) {
            $threatScore += 10;
            $indicators[] = "Ejecutable Mach-O de macOS detectado";
        }
        
        return [
            'threat_score' => $threatScore,
            'indicators' => $indicators
        ];
    }
    
    /**
     * BUSCAR STRINGS SOSPECHOSOS EN ARCHIVO
     */
    private function findSuspiciousStrings($filePath) {
        $suspiciousStrings = [
            'cmd.exe', 'powershell', 'wget', 'curl', 'bash',
            'reverse shell', 'metasploit', 'payload', 'exploit',
            'shellcode', 'ransomware', 'cryptolocker', 'wannacry',
            'trojan', 'keylogger', 'backdoor', 'rootkit',
            'eval(', 'exec(', 'system(', 'passthru(',
            'base64_decode', 'gzinflate', 'str_rot13',
            '/etc/passwd', 'shadow', 'sudoers'
        ];
        
        $found = [];
        $handle = fopen($filePath, 'rb');
        $chunk = fread($handle, 1024 * 1024); // Primeros 1MB
        fclose($handle);
        
        $content = strtolower($chunk);
        
        foreach ($suspiciousStrings as $pattern) {
            if (strpos($content, strtolower($pattern)) !== false) {
                $found[] = $pattern;
            }
        }
        
        return array_unique($found);
    }
    
    /**
     * ANÁLISIS DE INDICADORES COMPORTAMENTALES
     */
    private function analyzeBehavioralIndicators($filePath) {
        $score = 0;
        $indicators = [];
        
        $handle = fopen($filePath, 'rb');
        $content = fread($handle, 2 * 1024 * 1024); // Primeros 2MB
        fclose($handle);
        
        // Patrones de comportamiento malicioso
        $patterns = [
            'registry modification' => ['HKEY_', 'RegSetValue', 'RegDeleteKey'],
            'process injection' => ['VirtualAlloc', 'WriteProcessMemory', 'CreateRemoteThread'],
            'privilege escalation' => ['SeDebugPrivilege', 'AdjustTokenPrivileges'],
            'anti-debugging' => ['IsDebuggerPresent', 'CheckRemoteDebuggerPresent'],
            'anti-vm' => ['VBOX', 'VMware', 'VirtualBox', 'QEMU'],
            'network activity' => ['socket', 'connect', 'send', 'recv', 'HttpSendRequest'],
            'file operations' => ['CreateFile', 'WriteFile', 'DeleteFile', 'MoveFile'],
            'crypto operations' => ['CryptEncrypt', 'CryptDecrypt', 'CryptAcquireContext']
        ];
        
        foreach ($patterns as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($content, $keyword) !== false) {
                    $score += 3;
                    $indicators[] = "Comportamiento: $category ($keyword)";
                    break; // Solo contar una vez por categoría
                }
            }
        }
        
        return [
            'score' => min($score, 30),
            'indicators' => $indicators
        ];
    }
    
    /**
     * VIRUSTOTAL API
     */
    private function checkVirusTotal($identifier, $type = 'file', $url = null) {
        if (!$this->virustotalKey) return null;
        
        if ($type === 'url' && $url) {
            // Escanear URL
            $apiUrl = 'https://www.virustotal.com/api/v3/urls';
            $postData = ['url' => $url];
            
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'x-apikey: ' . $this->virustotalKey
            ]);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            $urlId = $data['data']['id'] ?? null;
            
            if (!$urlId) return null;
            
            // Esperar resultado
            sleep(2);
            $identifier = $urlId;
        }
        
        // Obtener reporte
        $apiUrl = "https://www.virustotal.com/api/v3/" . ($type === 'url' ? 'urls/' : 'files/') . $identifier;
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-apikey: ' . $this->virustotalKey
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) return null;
        
        $data = json_decode($response, true);
        $stats = $data['data']['attributes']['last_analysis_stats'] ?? [];
        
        return [
            'malicious' => $stats['malicious'] ?? 0,
            'suspicious' => $stats['suspicious'] ?? 0,
            'harmless' => $stats['harmless'] ?? 0,
            'undetected' => $stats['undetected'] ?? 0,
            'total' => array_sum($stats),
            'raw_data' => $data
        ];
    }
    
    /**
     * ANÁLISIS DE DOMINIO
     */
    private function analyzeDomain($url) {
        $threatScore = 0;
        $indicators = [];
        
        $parsed = parse_url($url);
        $domain = $parsed['host'] ?? '';
        
        // 1. Verificar si es IP en lugar de dominio
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            $threatScore += 20;
            $indicators[] = "URL usa dirección IP directa en lugar de dominio";
        }
        
        // 2. Longitud sospechosa
        if (strlen($domain) > 50) {
            $threatScore += 10;
            $indicators[] = "Dominio sospechosamente largo (" . strlen($domain) . " caracteres)";
        }
        
        // 3. Subdominios múltiples
        $parts = explode('.', $domain);
        if (count($parts) > 4) {
            $threatScore += 10;
            $indicators[] = "Múltiples subdominios detectados";
        }
        
        // 4. Caracteres sospechosos
        if (preg_match('/[^a-z0-9.-]/i', $domain)) {
            $threatScore += 15;
            $indicators[] = "Dominio contiene caracteres no estándar";
        }
        
        // 5. TLDs sospechosos
        $suspiciousTLDs = ['.tk', '.ml', '.ga', '.cf', '.gq', '.xyz', '.top', '.work', '.click'];
        foreach ($suspiciousTLDs as $tld) {
            if (substr($domain, -strlen($tld)) === $tld) {
                $threatScore += 15;
                $indicators[] = "TLD sospechoso: $tld";
                break;
            }
        }
        
        return [
            'domain' => $domain,
            'threat_score' => $threatScore,
            'indicators' => $indicators
        ];
    }
    
    /**
     * GOOGLE SAFE BROWSING
     */
    private function checkSafeBrowsing($url) {
        if (!$this->safeBrowsingKey) return null;
        
        $apiUrl = 'https://safebrowsing.googleapis.com/v4/threatMatches:find?key=' . $this->safeBrowsingKey;
        
        $payload = [
            'client' => [
                'clientId' => 'jorise',
                'clientVersion' => '1.0.0'
            ],
            'threatInfo' => [
                'threatTypes' => ['MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE', 'POTENTIALLY_HARMFUL_APPLICATION'],
                'platformTypes' => ['ANY_PLATFORM'],
                'threatEntryTypes' => ['URL'],
                'threatEntries' => [
                    ['url' => $url]
                ]
            ]
        ];
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (isset($data['matches']) && !empty($data['matches'])) {
            return [
                'threat_detected' => true,
                'threat_type' => $data['matches'][0]['threatType'] ?? 'UNKNOWN',
                'platform' => $data['matches'][0]['platformType'] ?? 'UNKNOWN'
            ];
        }
        
        return ['threat_detected' => false];
    }
    
    /**
     * ALIENVAULT OTX THREAT INTELLIGENCE
     */
    private function checkAlienVault($url) {
        if (!$this->alienvaultKey) return null;
        
        $domain = parse_url($url, PHP_URL_HOST);
        $apiUrl = "https://otx.alienvault.com/api/v1/indicators/domain/$domain/general";
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-OTX-API-KEY: ' . $this->alienvaultKey
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        return [
            'pulse_count' => $data['pulse_info']['count'] ?? 0,
            'reputation' => $data['reputation'] ?? 0,
            'data' => $data
        ];
    }
    
    /**
     * VERIFICAR SSL/HTTPS
     */
    private function checkSSL($url) {
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'] ?? '';
        
        if ($scheme !== 'https') {
            return [
                'valid' => false,
                'message' => 'URL no usa HTTPS (conexión no segura)',
                'has_certificate' => false
            ];
        }
        
        // Intentar verificar certificado
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);
        
        $client = @stream_socket_client(
            "ssl://{$host}:443",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if ($client === false) {
            return [
                'valid' => false,
                'message' => 'Certificado SSL inválido o expirado',
                'has_certificate' => false
            ];
        }
        
        $params = stream_context_get_params($client);
        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        
        fclose($client);
        
        return [
            'valid' => true,
            'message' => 'Certificado SSL válido',
            'has_certificate' => true,
            'issuer' => $cert['issuer']['O'] ?? 'Unknown',
            'valid_from' => date('Y-m-d', $cert['validFrom_time_t']),
            'valid_to' => date('Y-m-d', $cert['validTo_time_t'])
        ];
    }
    
    /**
     * DETECCIÓN DE PHISHING
     */
    private function detectPhishing($url) {
        $score = 0;
        $indicators = [];
        
        $parsed = parse_url($url);
        $domain = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';
        
        // Marcas comunes de phishing
        $brandKeywords = ['paypal', 'amazon', 'microsoft', 'google', 'facebook', 'apple', 'netflix', 'instagram', 'bank', 'secure', 'account', 'verify', 'login', 'update'];
        
        foreach ($brandKeywords as $brand) {
            if (stripos($domain, $brand) !== false || stripos($path, $brand) !== false) {
                // Verificar si NO es el dominio oficial
                if (!preg_match("/$brand\.(com|net|org)$/i", $domain)) {
                    $score += 25;
                    $indicators[] = "Posible imitación de marca: $brand";
                }
            }
        }
        
        // URL shorteners (pueden ocultar phishing)
        $shorteners = ['bit.ly', 'tinyurl.com', 'goo.gl', 't.co', 'ow.ly', 'is.gd'];
        foreach ($shorteners as $shortener) {
            if (stripos($domain, $shortener) !== false) {
                $score += 15;
                $indicators[] = "URL acortada (puede ocultar destino real)";
                break;
            }
        }
        
        // Uso de @ en URL (técnica de phishing)
        if (strpos($url, '@') !== false) {
            $score += 30;
            $indicators[] = "URL contiene '@' (técnica de phishing)";
        }
        
        // Muchos guiones en dominio
        if (substr_count($domain, '-') > 3) {
            $score += 10;
            $indicators[] = "Dominio con múltiples guiones (sospechoso)";
        }
        
        return [
            'score' => min($score, 50),
            'indicators' => $indicators
        ];
    }
    
    /**
     * GENERAR RECOMENDACIONES
     */
    private function generateRecommendations($analysis) {
        $recommendations = [];
        
        switch ($analysis['verdict']) {
            case 'malicious':
                $recommendations[] = "⛔ NO ABRIR/EJECUTAR - Amenaza confirmada";
                $recommendations[] = "🗑️ Eliminar inmediatamente";
                $recommendations[] = "🛡️ Ejecutar análisis completo del sistema";
                $recommendations[] = "🔒 Cambiar contraseñas si ya fue ejecutado";
                break;
                
            case 'suspicious':
                $recommendations[] = "⚠️ PRECAUCIÓN - Indicadores sospechosos detectados";
                $recommendations[] = "🔍 Realizar análisis adicional en entorno aislado";
                $recommendations[] = "📧 Verificar origen y autenticidad";
                $recommendations[] = "🚫 No ejecutar sin confirmación";
                break;
                
            case 'potentially_unwanted':
                $recommendations[] = "ℹ️ Revisar antes de continuar";
                $recommendations[] = "👁️ Verificar fuente y reputación";
                $recommendations[] = "🔐 Considerar alternativas más seguras";
                break;
                
            case 'clean':
                $recommendations[] = "✅ No se detectaron amenazas obvias";
                $recommendations[] = "🔍 Mantener precaución general";
                $recommendations[] = "📊 Considerar análisis periódicos";
                break;
        }
        
        return $recommendations;
    }
    
    /**
     * GENERAR REPORTE CON IA (GOOGLE GEMINI)
     */
    private function generateAIReport($analysis) {
        if (!$this->geminiKey) {
            return "Análisis con IA no disponible (configurar GEMINI_API_KEY)";
        }
        
        $prompt = $this->buildAIPrompt($analysis);
        
        $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $this->geminiKey;
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024
            ]
        ];
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return "Error al generar reporte con IA (HTTP $httpCode)";
        }
        
        $data = json_decode($response, true);
        $aiText = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'No se pudo generar el reporte';
        
        return $aiText;
    }
    
    /**
     * CONSTRUIR PROMPT PARA IA
     */
    private function buildAIPrompt($analysis) {
        $type = $analysis['analysis_type'];
        $target = $type === 'file' ? $analysis['file_name'] : $analysis['url'];
        
        $prompt = "Eres un analista de ciberseguridad experto. Genera un reporte profesional de análisis de malware.\n\n";
        $prompt .= "**ANÁLISIS DE " . strtoupper($type) . "**\n\n";
        $prompt .= "Objetivo: $target\n";
        $prompt .= "Veredicto: {$analysis['verdict']}\n";
        $prompt .= "Score de amenaza: {$analysis['threat_score']}/100\n";
        $prompt .= "Nivel de riesgo: {$analysis['threat_level']}\n\n";
        
        if (!empty($analysis['indicators'])) {
            $prompt .= "**Indicadores detectados:**\n";
            foreach ($analysis['indicators'] as $ind) {
                $prompt .= "- $ind\n";
            }
            $prompt .= "\n";
        }
        
        if (isset($analysis['virustotal'])) {
            $vt = $analysis['virustotal'];
            $prompt .= "**VirusTotal:** {$vt['malicious']}/{$vt['total']} motores detectaron amenaza\n\n";
        }
        
        $prompt .= "Genera un reporte que incluya:\n";
        $prompt .= "1. **Resumen Ejecutivo** (2-3 líneas)\n";
        $prompt .= "2. **Análisis Técnico** (detalles de los hallazgos)\n";
        $prompt .= "3. **Nivel de Riesgo** (crítico/alto/medio/bajo y por qué)\n";
        $prompt .= "4. **Recomendaciones** (acciones específicas)\n\n";
        $prompt .= "Formato: Markdown. Sé conciso pero técnico.";
        
        return $prompt;
    }
}
