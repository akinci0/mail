<?php
// basvuru_kaydet.php - PUAN ARALIKLI OTOMASYON SÜRÜMÜ

// Hataları gizle (JSON bozulmasın)
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
include 'baglanti.php';

if (file_exists('mail_helper.php')) include 'mail_helper.php';

// --- SENİN API ANAHTARIN ---
$apiKey = "AIzaSyA3bE6xanErBWWXWw7aXQJVQv5U2goBPuw"; 

$response = ["success" => false, "message" => "Bilinmeyen hata"];

function logYaz($mesaj) {
    $logFile = __DIR__ . "/ai_log.txt";
    file_put_contents($logFile, date("[Y-m-d H:i:s] ") . $mesaj . PHP_EOL, FILE_APPEND);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        logYaz("--- Yeni Başvuru İşlemi ---");

        $ad = $_POST['ad_soyad'] ?? '';
        $email = $_POST['email'] ?? '';
        $egitim = $_POST['egitim_durumu'] ?? '';
        $motivasyon = $_POST['motivasyon_metni'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'];
        $tc = $_POST['tc_kimlik'] ?? '00000000000'; 

        // 1. Dosya Yükleme
        function dosyaYukle($fileInputName, $prefix) {
            if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) return false;
            
            $target_dir = __DIR__ . "/uploads/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            
            $ext = strtolower(pathinfo($_FILES[$fileInputName]["name"], PATHINFO_EXTENSION));
            $yeniIsim = preg_replace('/[^a-zA-Z0-9]/', '', $prefix) . "_" . $fileInputName . "_" . time() . "." . $ext;
            $path = $target_dir . $yeniIsim;
            $web_path = "uploads/" . $yeniIsim;

            if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $path)) return ["full" => $path, "web" => $web_path];
            return false;
        }

        $cv = dosyaYukle("ozgecmis", $ad);
        $belge = dosyaYukle("belge", $ad);

        if (!$cv || !$belge) throw new Exception("Dosyalar yüklenemedi.");

        // 2. AI ANALİZİ (YENİ PUAN SİSTEMİ İLE)
        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;
        $belgeData = base64_encode(file_get_contents($belge['full']));
        $mimeType = mime_content_type($belge['full']);

        // --- GÜNCELLENEN PROMPT ---
        $promptMetni = "SENİN ROLÜN: PiA Şirketi İK Yöneticisi.
        
        GÖREVLERİN:
        1. ÖĞRENCİ BELGESİ KONTROLÜ:
           - Belge sahteyse veya alakasızsa (manzara, kedi vb.) -> PUAN: 0, KARAR: 'ret'.
           - Belge geçerliyse 2. adıma geç.

        2. MOTİVASYON ANALİZİ ('$motivasyon'):
           - Metni ciddiyet, heves ve yazılım ilgisine göre 0-100 arası puanla.
           - 'asdasd', 'merhaba', 'staj istiyorum' gibi baştan savma metinlere -> PUAN: 20.
        
        KARAR MANTIĞI (PUANA GÖRE):
        - 0 - 59 Puan: Yetersiz -> KARAR: 'ret'
        - 60 - 84 Puan: Potansiyel Var -> KARAR: 'incelendi'
        - 85 - 100 Puan: Mükemmel -> KARAR: 'kabul'
        
        ÇIKTI FORMATI (SADECE JSON):
        {
          \"puan\": (Sayı),
          \"ozet\": \"(Kısa Türkçe yorum)\",
          \"karar\": \"kabul\", \"incelendi\" veya \"ret\"
        }";

        $data = ["contents" => [["parts" => [["text" => $promptMetni], ["inline_data" => ["mime_type" => $mimeType, "data" => $belgeData]]]]]];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        $ai_response = curl_exec($ch);
        curl_close($ch);

        // 3. Sonucu İşle ve Karar Ver
        $aiPuan = 0; $aiOzet = "AI Kararsız."; $durum = 'beklemede';
        $jsonAI = json_decode($ai_response, true);
        
        if (isset($jsonAI['candidates'][0]['content']['parts'][0]['text'])) {
            $text = str_replace(['```json', '```'], '', $jsonAI['candidates'][0]['content']['parts'][0]['text']);
            $sonuc = json_decode($text, true);
            if ($sonuc) {
                $aiPuan = intval($sonuc['puan'] ?? 0);
                $aiOzet = $sonuc['ozet'] ?? '';
                
                // --- PHP TARAFINDA KESİN KURAL UYGULAMASI ---
                if ($aiPuan >= 85) {
                    $durum = 'kabul';
                    $aiOzet .= " (AI: Üstün Başarı)";
                    // KABUL -> Sınav Daveti Gönder
                    if (function_exists('mailGonder')) mailGonder($email, $ad, 'davet');
                } 
                elseif ($aiPuan >= 60) {
                    $durum = 'incelendi';
                    $aiOzet .= " (AI: İncelemeye Uygun)";
                    // İNCELENDİ -> Mail gitmez, havuzda bekler.
                } 
                else {
                    $durum = 'ret';
                    $aiOzet .= " (AI: Yetersiz)";
                    // RET -> Ret Maili Gönder
                    if (function_exists('mailGonder')) mailGonder($email, $ad, 'ret');
                }
            }
        } elseif(isset($jsonAI['error'])) {
            $aiOzet = "AI Hatası: " . $jsonAI['error']['message'];
        }

        // 4. Veritabanı Kayıt
        $sql = "INSERT INTO basvurular (tc_kimlik, ad_soyad, email, egitim_durumu, motivasyon_metni, ozgecmis_path, ogrenci_belgesi_path, ip_adresi, durum, ai_puani, ai_ozeti) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssis", $tc, $ad, $email, $egitim, $motivasyon, $cv['web'], $belge['web'], $ip, $durum, $aiPuan, $aiOzet);

        if ($stmt->execute()) {
            $response["success"] = true;
            
            // Kullanıcıya gösterilecek mesaj
            if ($durum == 'kabul') {
                $response["message"] = "Tebrikler! Başvurunuz AI ön elemesini geçti. Sınav davetiyeniz e-postanıza gönderildi.";
            } elseif ($durum == 'incelendi') {
                $response["message"] = "Başvurunuz alındı ve İK havuzuna eklendi. Değerlendirme devam ediyor.";
            } else {
                $response["message"] = "Başvurunuz kriterlerimize uymadığı için maalesef kabul edilemedi.";
            }
            
        } else {
            throw new Exception("Veritabanı Hatası.");
        }
        $stmt->close();

    } catch (Exception $e) {
        logYaz("HATA: " . $e->getMessage());
        $response["success"] = false;
        $response["message"] = "Bir hata oluştu: " . $e->getMessage();
    }
}

echo json_encode($response);
?>