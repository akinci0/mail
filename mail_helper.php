<?php
// mail_helper.php - PROFESYONEL RET MESAJI SÜRÜMÜ

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Dosyalar yan yana olmalı
if (file_exists('PHPMailer.php')) {
    require 'Exception.php';
    require 'PHPMailer.php';
    require 'SMTP.php';
}

function mailGonder($aliciEmail, $aliciAd, $durum = 'davet', $puan = 0) {
    $mail = new PHPMailer(true);

    try {
        // --- GMAIL AYARLARI (LÜTFEN KENDİ BİLGİLERİNİ GİR) ---
        $mail->isSMTP();                                            
        $mail->Host       = 'smtp.gmail.com';                     
        $mail->SMTPAuth   = true;                                   
        $mail->Username   = 'yusuf.cskn163@gmail.com'; // <--- DOLDUR
        $mail->Password   = 'yncx dncd wltd drsk';    // <--- DOLDUR
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;            
        $mail->Port       = 587;                                    
        $mail->CharSet    = 'UTF-8';

        // SSL Hatası Çözümü
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // --- GÖNDEREN ---
        $mail->setFrom('kariyer@pia.com', 'PiA İnsan Kaynakları');
        $mail->addAddress($aliciEmail, $aliciAd);     

        $mail->isHTML(true);

        // --- İÇERİK SENARYOLARI ---
        
        // SENARYO 1: Sınav Daveti (Başvuru Sonrası)
        if ($durum == 'davet') {
            $link = "http://localhost:8888/pia/index.php";
            $mail->Subject = "Tebrikler! PiA Bootcamp İngilizce Sınav Daveti";
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; color: #333; padding: 20px; border: 1px solid #eee; border-radius: 8px;'>
                    <h2 style='color: #463e66;'>Sayın $aliciAd,</h2>
                    <p>PiA Yazılım Geliştirme Kampı başvurunuz, yapay zeka destekli ön değerlendirme sürecimizi başarıyla geçmiştir.</p>
                    <p>Sürecin bir sonraki adımı olan <b>İngilizce Seviye Tespit Sınavı</b>'na katılmaya hak kazandınız.</p>
                    <br>
                    <a href='$link' style='background-color: #00ADB5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Sınavı Başlat</a>
                    <br><br>
                    <p style='font-size: 12px; color: #666;'>Giriş yapmak için E-posta adresinizi ve TC Kimlik numaranızı kullanabilirsiniz.</p>
                </div>";
        }
        
        // SENARYO 2: Sınav Başarılı (Kabul - Final)
        elseif ($durum == 'kabul') {
            $mail->Subject = "Tebrikler! PiA Staj Programına Kabul Edildiniz 🎉";
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; color: #333; padding: 20px; border: 1px solid #d4edda; background-color: #f0fff4; border-radius: 8px;'>
                    <h2 style='color: #28a745;'>Tebrikler $aliciAd!</h2>
                    <p>İngilizce sınavından <b>$puan</b> puan alarak başarı kriterlerimizi sağladınız.</p>
                    <p>Başvurunuz <b>KABUL EDİLMİŞTİR</b>. İK ekibimiz en kısa sürede sizinle iletişime geçecektir.</p>
                    <br>
                    <p>Aramıza hoş geldiniz!</p>
                </div>";
        }
        
        // SENARYO 3: RET (AI veya Sınav Başarısızlığı - PROFESYONEL)
        else {
            $mail->Subject = "PiA Bootcamp Başvurunuz Hakkında Bilgilendirme";
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; color: #444; line-height: 1.6; padding: 30px; border: 1px solid #eee; border-radius: 8px; background-color: #fdfdfd;'>
                    <h3 style='color: #463e66; margin-top: 0;'>Sayın $aliciAd,</h3>
                    
                    <p>Öncelikle PiA Yazılım Geliştirme Kampı'na gösterdiğiniz ilgi ve başvuru sürecinde ayırdığınız zaman için teşekkür ederiz.</p>
                    
                    <p>Yaptığımız titiz değerlendirmeler ve yoğun başvuru süreci sonucunda, başvurunuzu maalesef bu dönem için <b>olumlu olarak değerlendiremediğimizi</b> bildirmek isteriz.</p>
                    
                    <div style='background-color: #fff3f3; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0; font-size: 14px; color: #555;'>
                        Bu karar, potansiyelinizin bir göstergesi olmayıp, sadece mevcut programın spesifik kriterleri ve kontenjan durumuyla ilgilidir.
                    </div>

                    <p>Gelecekte açılacak yeni programlarımızda ve kariyer fırsatlarımızda sizi tekrar aramızda görmekten mutluluk duyarız.</p>
                    
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 25px 0;'>
                    
                    <p style='font-size: 14px; color: #666;'>
                        Kariyer yolculuğunuzda başarılar dileriz.<br>
                        <b>PiA İnsan Kaynakları Ekibi</b>
                    </p>
                </div>";
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        file_put_contents("mail_hata.txt", "Mail Hatası: " . $mail->ErrorInfo . "\n", FILE_APPEND);
        return "Hata: " . $mail->ErrorInfo;
    }
}
?>