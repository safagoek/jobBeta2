import mysql.connector
import os
import PyPDF2
import json
import time
import datetime
import re
from openai import OpenAI

# Script başlangıç zamanı
start_time = datetime.datetime.now()
print(f"Açık Uçlu Cevap Değerlendirme Sistemi başladı: {start_time}")

# Database configuration
DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "job_application_system_db"
}

# OpenRouter API configuration
OPENROUTER_API_KEY = "" #api_key_buraya
YOUR_SITE_URL = "http://localhost"
YOUR_SITE_NAME = "Answer Evaluation System"

# Web kök dizini
BASE_DIRECTORY = r"C:\xampp\htdocs\jobBeta3"

# OpenAI client with OpenRouter
client = OpenAI(
    base_url="https://openrouter.ai/api/v1",
    api_key=OPENROUTER_API_KEY,
)

def get_full_path(relative_path):
    """Veritabanından gelen göreceli yolları tam dosya yoluna dönüştürür."""
    normalized_path = relative_path.replace('/', os.path.sep)
    full_path = os.path.join(BASE_DIRECTORY, normalized_path)
    return full_path

def extract_text_from_pdf(relative_path):
    """PDF dosyasından metin çıkarır"""
    try:
        full_path = get_full_path(relative_path)
        print(f"PDF okunuyor: {full_path}")
        
        if not os.path.exists(full_path):
            print(f"PDF dosyası bulunamadı: {full_path}")
            return None
        
        text = ""
        with open(full_path, 'rb') as file:
            reader = PyPDF2.PdfReader(file)
            for page_num in range(len(reader.pages)):
                page_text = reader.pages[page_num].extract_text()
                if page_text:
                    text += page_text + "\n"
        
        if not text.strip():
            print(f"PDF'den metin çıkarılamadı: {full_path}")
            return None
            
        print(f"PDF'den {len(text)} karakter metin çıkarıldı")
        return text.strip()
    except Exception as e:
        print(f"PDF okuma hatası ({relative_path}): {str(e)}")
        return None

def get_question_context_from_templates(connection, question_id):
    """Template sisteminden soru bilgisini çeker"""
    try:
        cursor = connection.cursor(dictionary=True)
        
        # Template_questions tablosundan soru bilgisini al
        cursor.execute("""
            SELECT tq.id, tq.question_text, tq.question_type, 
                   qt.template_name, qt.category, qt.description as template_description
            FROM template_questions tq
            JOIN question_templates qt ON tq.template_id = qt.id
            WHERE tq.id = %s
        """, (question_id,))
        
        question_data = cursor.fetchone()
        cursor.close()
        
        if not question_data:
            print(f"Template soru ID {question_id} bulunamadı")
            return None
        
        # Bağlamı oluştur
        context = f"Soru Şablonu: {question_data['template_name']}\n"
        context += f"Kategori: {question_data['category'] or 'Belirtilmemiş'}\n"
        context += f"Şablon Açıklaması: {question_data['template_description'] or 'Yok'}\n\n"
        context += f"Soru Tipi: {question_data['question_type']}\n"
        context += f"Soru: {question_data['question_text']}"
        
        return context
        
    except Exception as e:
        print(f"Template soru bilgisi alınırken hata: {str(e)}")
        return None

def ensure_answer_feedback_column(connection):
    """application_answers tablosuna answer_feedback kolonu ekler (yoksa)"""
    try:
        cursor = connection.cursor()
        
        cursor.execute("""
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = 'application_answers' 
            AND COLUMN_NAME = 'answer_feedback'
        """, (DB_CONFIG['database'],))
        
        if not cursor.fetchone():
            print("answer_feedback sütunu oluşturuluyor...")
            cursor.execute("""
                ALTER TABLE application_answers
                ADD COLUMN answer_feedback TEXT COMMENT 'AI tarafından oluşturulan cevap geri bildirimi'
            """)
            connection.commit()
            print("answer_feedback sütunu başarıyla oluşturuldu")
        
        cursor.close()
        return True
    except Exception as e:
        print(f"answer_feedback sütunu kontrol/oluşturma hatası: {str(e)}")
        return False

def extract_evaluation_from_response(response_text):
    """AI yanıtından puan ve geri bildirimi güvenli bir şekilde çıkarır"""
    try:
        # JSON formatını bulmaya çalış
        json_start = response_text.find('{')
        json_end = response_text.rfind('}') + 1
        
        if json_start >= 0 and json_end > json_start:
            json_str = response_text[json_start:json_end]
            try:
                result = json.loads(json_str)
                score = int(result.get('score', 0))
                feedback = result.get('feedback', 'Geri bildirim çıkarılamadı')
                
                # Puanı 0-100 aralığında sınırla
                score = max(0, min(100, score))
                
                return {"score": score, "feedback": feedback}
            except (json.JSONDecodeError, ValueError, TypeError):
                pass
        
        # JSON bulunamazsa regex ile arama yap
        score_patterns = [
            r'"score"\s*:\s*(\d+)',
            r"'score'\s*:\s*(\d+)",
            r'score\s*:\s*(\d+)',
            r'puan\s*:\s*(\d+)',
        ]
        
        feedback_patterns = [
            r'"feedback"\s*:\s*"([^"]+)"',
            r"'feedback'\s*:\s*'([^']+)'",
            r'feedback\s*:\s*"([^"]+)"',
        ]
        
        score = 50  # Varsayılan puan
        feedback = "Cevap değerlendirildi ancak detaylı geri bildirim çıkarılamadı"
        
        # Puan arama
        for pattern in score_patterns:
            match = re.search(pattern, response_text, re.IGNORECASE)
            if match:
                try:
                    score = max(0, min(100, int(match.group(1))))
                    break
                except (ValueError, TypeError):
                    continue
        
        # Geri bildirim arama
        for pattern in feedback_patterns:
            match = re.search(pattern, response_text, re.IGNORECASE | re.DOTALL)
            if match:
                feedback = match.group(1).strip()
                break
        
        return {"score": score, "feedback": feedback}
        
    except Exception as e:
        print(f"Yanıt işleme hatası: {str(e)}")
        return {"score": 0, "feedback": f"Değerlendirme işlenirken hata: {str(e)}"}

def evaluate_open_ended_answer(question_context, answer_text):
    """Açık uçlu sorunun cevabını AI ile değerlendirir"""
    try:
        if not answer_text or not answer_text.strip():
            return {"score": 0, "feedback": "Cevap boş veya içerik bulunamadı."}
        
        prompt = f"""
Aşağıdaki açık uçlu soruya verilen cevabı profesyonel olarak değerlendir:

SORU VE BAĞLAM:
{question_context}

ADAYIN CEVABI:
{answer_text}

Değerlendirme kriterleri:
1. İçerik kalitesi ve doğruluk
2. Soruya uygunluk ve kapsamlılık  
3. Profesyonellik ve ifade becerileri
4. Özgünlük ve yaratıcılık
5. İş pozisyonuna uygunluk
6. Teknik bilgi seviyesi (varsa)
7. En Düşük 1 puan ver 

Lütfen yanıtını SADECE şu JSON formatında ver:
{{
  "score": (0-100 arası tam sayı),
  "feedback": "detaylı değerlendirme açıklaması"
}}
"""
        
        print("AI değerlendirmesi için OpenRouter API'ya istek gönderiliyor...")
        
        completion = client.chat.completions.create(
            extra_headers={
                "HTTP-Referer": YOUR_SITE_URL,
                "X-Title": YOUR_SITE_NAME,
            },
            model="deepseek/deepseek-r1:free",
            messages=[
                {
                    "role": "system", 
                    "content": "Sen profesyonel bir işe alım uzmanısın. Açık uçlu soru cevaplarını objektif olarak değerlendiriyorsun. Yanıtlarını her zaman JSON formatında veriyorsun."
                },
                {
                    "role": "user",
                    "content": prompt
                }
            ],
            temperature=0.3,
            max_tokens=1500
        )
        
        response_text = completion.choices[0].message.content
        print(f"AI yanıtı alındı ({len(response_text)} karakter)")
        
        return extract_evaluation_from_response(response_text)
        
    except Exception as e:
        print(f"AI değerlendirme hatası: {str(e)}")
        return {"score": 0, "feedback": f"Değerlendirme sırasında hata oluştu: {str(e)}"}

def process_open_ended_answers():
    """Sadece option_id NULL olan (açık uçlu) cevapları işler ve değerlendirir"""
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        
        if not ensure_answer_feedback_column(connection):
            print("Gerekli veritabanı yapısı sağlanamadı, işlem durduruluyor")
            return
        
        cursor = connection.cursor(dictionary=True)
        
        # Sadece option_id NULL olan kayıtları getir (açık uçlu sorular)
        query = """
            SELECT aa.id, aa.application_id, aa.question_id, 
                   aa.answer_text, aa.answer_file_path, aa.answer_score,
                   a.first_name, a.last_name
            FROM application_answers aa
            JOIN applications a ON aa.application_id = a.id
            WHERE aa.option_id IS NULL 
              AND aa.answer_score = 0
              AND (
                  (aa.answer_text IS NOT NULL AND TRIM(aa.answer_text) != '') 
                  OR 
                  (aa.answer_file_path IS NOT NULL AND TRIM(aa.answer_file_path) != '')
              )
            ORDER BY aa.id ASC
        """
        
        cursor.execute(query)
        answers_to_process = cursor.fetchall()
        
        print(f"\n{'='*60}")
        print(f"AÇIK UÇLU CEVAP DEĞERLENDİRME SİSTEMİ")
        print(f"{'='*60}")
        print(f"Toplam işlenecek cevap sayısı: {len(answers_to_process)}")
        print(f"Template sisteminden sorular alınacak")
        print(f"{'='*60}\n")
        
        processed_count = 0
        
        for i, answer in enumerate(answers_to_process, 1):
            answer_id = answer['id']
            question_id = answer['question_id']
            applicant_name = f"{answer['first_name']} {answer['last_name']}"
            
            print(f"[{i}/{len(answers_to_process)}] Cevap ID: {answer_id} işleniyor")
            print(f"Aday: {applicant_name}")
            print(f"Template Soru ID: {question_id}")
            
            # Cevap içeriğini belirle
            answer_content = None
            content_source = None
            
            if answer['answer_text'] and answer['answer_text'].strip():
                answer_content = answer['answer_text'].strip()
                content_source = "metin"
                print(f"Cevap türü: Metin ({len(answer_content)} karakter)")
                
            elif answer['answer_file_path'] and answer['answer_file_path'].strip():
                file_path = answer['answer_file_path'].strip()
                content_source = "dosya"
                print(f"Cevap türü: Dosya ({file_path})")
                answer_content = extract_text_from_pdf(file_path)
            
            if not answer_content:
                print("❌ Cevap içeriği alınamadı, atlanıyor\n")
                continue
            
            # Template sisteminden soru bağlamını al
            question_context = get_question_context_from_templates(connection, question_id)
            if not question_context:
                print("❌ Template soru bilgisi alınamadı, atlanıyor\n")
                continue
            
            print("✅ Soru ve cevap hazır, AI değerlendirmesi başlatılıyor...")
            
            # AI ile değerlendir
            evaluation = evaluate_open_ended_answer(question_context, answer_content)
            
            score = evaluation['score']
            feedback = evaluation['feedback']
            
            print(f"📊 Değerlendirme tamamlandı:")
            print(f"   Puan: {score}/100")
            print(f"   Geri bildirim: {feedback[:100]}...")
            
            # Veritabanını güncelle
            try:
                update_cursor = connection.cursor()
                update_cursor.execute("""
                    UPDATE application_answers 
                    SET answer_score = %s, answer_feedback = %s
                    WHERE id = %s
                """, (score, feedback, answer_id))
                
                connection.commit()
                update_cursor.close()
                
                processed_count += 1
                print(f"✅ Veritabanı güncellendi")
                
            except Exception as e:
                print(f"❌ Veritabanı güncelleme hatası: {str(e)}")
            
            print(f"{'-'*50}\n")
            
            # API rate limiting
            time.sleep(1)
        
        cursor.close()
        connection.close()
        
        print(f"\n{'='*60}")
        print(f"İŞLEM TAMAMLANDI!")
        print(f"Toplam işlenen cevap: {processed_count}/{len(answers_to_process)}")
        print(f"{'='*60}")
        
    except Exception as e:
        print(f"❌ Genel işlem hatası: {str(e)}")

if __name__ == "__main__":
    try:
        process_open_ended_answers()
    except Exception as e:
        print(f"❌ Program hatası: {str(e)}")
    finally:
        end_time = datetime.datetime.now()
        print(f"\nSistem kapatıldı: {end_time}")
        print(f"Toplam çalışma süresi: {end_time - start_time}")