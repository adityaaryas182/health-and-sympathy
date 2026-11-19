-- DIHASILKAN OTOMATIS oleh bank_soal/generate.php dari bank_soal.txt. Jangan edit manual.
-- Jalankan SETELAH migrasi_bank_soal_2026-11-13.sql.
-- PERHATIAN: menghapus semua soal, skill, jawaban_siswa, dan bkt_siswa lama.

SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM jawaban_siswa;
DELETE FROM bkt_siswa;
DELETE FROM soal;
DELETE FROM skill;
ALTER TABLE jawaban_siswa AUTO_INCREMENT = 1;
ALTER TABLE bkt_siswa AUTO_INCREMENT = 1;
ALTER TABLE soal AUTO_INCREMENT = 1;
ALTER TABLE skill AUTO_INCREMENT = 1;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO skill (id_skill, nama_skill, level_game, materi, p_init, p_learn, p_guess, p_slip) VALUES
(1, 'Expressing Sympathy', 2, 'Sympathy Expressions', 0.20, 0.10, 0.25, 0.10),
(2, 'Responding to Sympathy', 2, 'Sympathy Expressions', 0.20, 0.10, 0.25, 0.10),
(3, 'Asking about Health', 1, 'Asking and Giving Information about Health', 0.20, 0.10, 0.25, 0.10),
(4, 'Giving Information about Health', 1, 'Asking and Giving Information about Health', 0.20, 0.10, 0.25, 0.10),
(5, 'Simple Present: Positive Sentences', 3, 'Simple Present Tense', 0.20, 0.10, 0.25, 0.10),
(6, 'Simple Present: Negative Sentences and Questions', 3, 'Simple Present Tense', 0.20, 0.10, 0.25, 0.10);

INSERT INTO soal (id_skill, level_game, tingkat, pertanyaan, pilihan_a, pilihan_b, pilihan_c, pilihan_d, jawaban_benar, pembahasan, status) VALUES
(1, 2, 1, 'Your friend is sick. What do you say?', 'Get well soon!', 'Congratulations!', 'Happy birthday!', 'Good job!', 'A', '"Get well soon!" artinya "Semoga cepat sembuh!", dipakai untuk orang yang sedang sakit.', 'aktif'),
(1, 2, 1, 'Which one is an expression of sympathy?', 'Nice to meet you.', 'See you later.', 'What a beautiful day!', 'I''m sorry to hear that.', 'D', '"I''m sorry to hear that" artinya "Aku turut sedih mendengarnya". Ini ungkapan simpati.', 'aktif'),
(1, 2, 1, 'Rina''s cat is lost. You say, "...."', 'That''s great, Rina!', 'Oh, poor you. I hope you find it soon.', 'Well done, Rina!', 'I''m happy for you.', 'B', 'Rina sedang sedih karena kucingnya hilang, jadi kita menunjukkan simpati, bukan ikut senang.', 'aktif'),
(1, 2, 1, 'Your teacher has a toothache. You say, "...."', 'I hope you feel better soon.', 'Have a nice trip!', 'Happy New Year!', 'You''re welcome.', 'A', '"I hope you feel better soon" artinya "Semoga cepat merasa lebih baik", cocok untuk orang yang sakit.', 'aktif'),
(1, 2, 1, 'We say "I''m sorry to hear that" when someone ....', 'wins a game', 'gets a present', 'tells us bad news', 'has a birthday', 'C', 'Ungkapan simpati dipakai saat orang lain mengalami hal yang menyedihkan atau kabar buruk.', 'aktif'),
(1, 2, 1, 'Budi falls off his bike and hurts his knee. You say, "...."', 'Congratulations, Budi!', 'Oh dear! Are you okay?', 'Good morning, Budi.', 'Thank you, Budi.', 'B', '"Oh dear! Are you okay?" menunjukkan kita peduli pada Budi yang baru saja jatuh.', 'aktif'),
(1, 2, 2, 'Sinta: "My grandmother is in the hospital."
Dika: "...."', 'That sounds fun!', 'I''m sorry to hear that. I hope she gets well soon.', 'Good for you, Sinta!', 'Don''t worry, it''s not important.', 'B', 'Nenek Sinta sakit, jadi Dika menyampaikan simpati dan mendoakan kesembuhan.', 'aktif'),
(1, 2, 2, 'Which situation needs an expression of sympathy?', 'Your friend''s house is flooded.', 'Your friend gets the best score.', 'Your friend has a new bag.', 'Your friend is going on holiday.', 'A', 'Rumah kebanjiran adalah musibah. Situasi lainnya adalah kabar gembira.', 'aktif'),
(1, 2, 2, 'Lina: "I can''t join the school trip. I have a fever."
You: "...."', 'Lucky you!', 'Have a nice trip, Lina!', 'What a pity! Get well soon, Lina.', 'That''s a good idea.', 'C', '"What a pity!" artinya "Sayang sekali!", lalu ditambah doa agar Lina cepat sembuh.', 'aktif'),
(1, 2, 2, 'Which expression is NOT an expression of sympathy?', 'Poor you.', 'I''m sorry to hear that.', 'Get well soon.', 'Congratulations!', 'D', '"Congratulations!" adalah ucapan selamat untuk kabar gembira, bukan ungkapan simpati.', 'aktif'),
(1, 2, 2, 'Andi: "My dog died yesterday."
Choose the best response.', 'Really? That''s funny.', 'Oh no, I''m so sorry. You must be very sad.', 'Great! Now you have more free time.', 'Okay. Let''s play football.', 'B', 'Andi sedang berduka, jadi respons yang tepat adalah menunjukkan simpati atas kesedihannya.', 'aktif'),
(1, 2, 2, 'Mira: "I broke my arm when I played basketball."
Tono: "...., Mira. I hope it heals quickly."', 'How lucky', 'Well done', 'That''s terrible', 'How nice', 'C', '"That''s terrible" artinya "Itu mengerikan/menyedihkan", cocok untuk kabar buruk seperti tangan patah.', 'aktif'),
(2, 2, 1, 'Your friend says, "Get well soon!" You answer, "...."', 'You''re sick.', 'Congratulations!', 'Thank you.', 'Good night.', 'C', 'Saat orang menunjukkan simpati, kita membalasnya dengan berterima kasih.', 'aktif'),
(2, 2, 1, 'Which one is a response to sympathy?', 'I''m sorry to hear that.', 'Thanks for your concern.', 'Happy birthday!', 'Poor you.', 'B', '"Thanks for your concern" artinya "Terima kasih atas perhatianmu". "I''m sorry to hear that" dan "Poor you" adalah ungkapan simpati, bukan responsnya.', 'aktif'),
(2, 2, 1, 'Teacher: "I''m sorry to hear about your father."
You: "...."', 'You''re welcome.', 'Me too.', 'Nice to meet you.', 'Thank you, Ma''am.', 'D', 'Simpati dari guru dibalas dengan ucapan terima kasih. "You''re welcome" adalah balasan untuk "thank you".', 'aktif'),
(2, 2, 1, '"Thank you for your sympathy." We use this sentence to ....', 'give sympathy', 'ask for help', 'say goodbye', 'respond to sympathy', 'D', 'Kalimat ini dipakai untuk membalas simpati dari orang lain.', 'aktif'),
(2, 2, 1, 'Dita: "Poor you! I hope you feel better soon."
Rudi: "...."', 'Poor you too, Dita.', 'Yes, you are sick.', 'Thanks, Dita. I hope so too.', 'Good morning, Dita.', 'C', 'Rudi berterima kasih atas simpati Dita dan ikut berharap segera membaik.', 'aktif'),
(2, 2, 1, 'Arrange the words: concern - you - your - for - thank', 'Thank you for your concern.', 'Thank your concern for you.', 'Your concern thank you for.', 'For your concern you thank.', 'A', 'Susunan yang benar adalah "Thank you for your concern", artinya "Terima kasih atas perhatianmu".', 'aktif'),
(2, 2, 2, 'Nina: "I heard you had an accident. Are you okay?"
Toni: "...."', 'I''m okay now. Thank you for asking.', 'I''m sorry to hear that.', 'Congratulations, Nina!', 'You''re welcome, Nina.', 'A', 'Toni menjawab keadaannya lalu berterima kasih karena Nina sudah peduli.', 'aktif'),
(2, 2, 2, 'Bayu: "I''m sorry your bike was stolen."
What is the best response?', 'Poor you, Bayu.', 'Congratulations, Bayu!', 'Yes, I stole it.', 'Thanks, Bayu. That''s very kind of you.', 'D', '"That''s very kind of you" artinya "Kamu baik sekali", cara sopan membalas simpati.', 'aktif'),
(2, 2, 2, 'Which dialogue is correct?', 'A: "Get well soon!" B: "Thank you very much."', 'A: "Get well soon!" B: "You''re welcome."', 'A: "Get well soon!" B: "Get well soon too."', 'A: "Get well soon!" B: "I''m sorry to hear that."', 'A', '"Get well soon" dibalas dengan terima kasih. "You''re welcome" adalah balasan untuk "thank you".', 'aktif'),
(2, 2, 2, 'Mother: "Don''t be sad. Everything will be okay."
Siti: "...."', 'Don''t be sad too, Mom.', 'Okay, see you tomorrow.', 'Thank you, Mom. You always make me feel better.', 'Happy birthday, Mom!', 'C', 'Siti berterima kasih karena ibunya menghibur dan menunjukkan simpati.', 'aktif'),
(2, 2, 2, 'Ani: "I''m sorry to hear you lost your wallet."
Rina: "Thanks, Ani. ...."', 'I''m happy to lose it.', 'You lost your wallet too.', 'Congratulations to me.', 'I hope someone finds it.', 'D', 'Setelah berterima kasih, Rina menyampaikan harapannya agar dompetnya ditemukan.', 'aktif'),
(2, 2, 2, 'Which sentence is a polite response to sympathy?', 'Leave me alone!', 'It''s very kind of you to say so.', 'That''s your problem.', 'I don''t care.', 'B', '"It''s very kind of you to say so" artinya "Baik sekali kamu berkata begitu". Pilihan lainnya tidak sopan.', 'aktif'),
(3, 1, 1, 'We want to know how someone feels. We ask, "...."', 'Where do you live?', 'How are you feeling?', 'What is your name?', 'How old are you?', 'B', '"How are you feeling?" artinya "Bagaimana perasaanmu/keadaanmu?", untuk menanyakan kondisi kesehatan.', 'aktif'),
(3, 1, 1, 'Which question asks about someone''s health?', 'What time is it?', 'What is your hobby?', 'Where is the library?', 'What''s wrong with you?', 'D', '"What''s wrong with you?" artinya "Kamu kenapa?", menanyakan keluhan kesehatan.', 'aktif'),
(3, 1, 1, 'Your friend looks pale. You ask, "...."', 'Is this your book?', 'Are you sick?', 'Are you a student?', 'Are you ready to play?', 'B', 'Teman terlihat pucat, jadi kita menanyakan apakah ia sakit.', 'aktif'),
(3, 1, 1, '"What''s the matter?" means ....', 'Ada apa? / Kenapa?', 'Kamu tinggal di mana?', 'Siapa namamu?', 'Jam berapa sekarang?', 'A', '"What''s the matter?" dipakai untuk menanyakan masalah atau keluhan seseorang.', 'aktif'),
(3, 1, 1, 'Doctor: "Where does it hurt?"
The doctor asks about ....', 'the patient''s address', 'the patient''s school', 'the part of the body that hurts', 'the patient''s hobby', 'C', '"Where does it hurt?" artinya "Bagian mana yang sakit?"', 'aktif'),
(3, 1, 1, 'Choose the correct question to ask about health.', 'How do you feel today?', 'How much is this?', 'How many books do you have?', 'How do you go to school?', 'A', '"How do you feel today?" menanyakan keadaan seseorang hari ini. Pertanyaan lain tentang harga, jumlah, dan cara ke sekolah.', 'aktif'),
(3, 1, 2, 'Dina: "...."
Raka: "I have a stomachache."', 'Where are you going, Raka?', 'What do you like, Raka?', 'When is your birthday, Raka?', 'What''s wrong, Raka?', 'D', 'Raka menjawab keluhan sakit perut, jadi pertanyaannya menanyakan apa yang salah dengan dirinya.', 'aktif'),
(3, 1, 2, 'Nurse: "...."
Patient: "Since yesterday morning."', 'Where does it hurt?', 'When did the pain start?', 'How are you?', 'What is your name?', 'B', 'Jawaban "Since yesterday morning" menunjukkan waktu, jadi pertanyaannya "Kapan sakitnya mulai?"', 'aktif'),
(3, 1, 2, 'Doctor: "...."
Patient: "My head hurts."', 'When did you come?', 'Who is your mother?', 'Where does it hurt?', 'How old are you?', 'C', 'Pasien menyebut bagian tubuh yang sakit, jadi dokter bertanya "Bagian mana yang sakit?"', 'aktif'),
(3, 1, 2, 'Which question is NOT about health?', 'What''s your favorite color?', 'Do you have a fever?', 'Is your throat sore?', 'How do you feel now?', 'A', '"What''s your favorite color?" menanyakan warna kesukaan, bukan kesehatan.', 'aktif'),
(3, 1, 2, 'Your friend coughs a lot in class. What is the best question?', 'Can you cough louder?', 'Are you okay? Do you have a cold?', 'Do you want to play outside?', 'Why don''t you sing?', 'B', 'Teman yang banyak batuk sebaiknya ditanya keadaannya dan apakah ia sedang pilek.', 'aktif'),
(3, 1, 2, 'Mother: "You look tired. ...."
Ayu: "I''m fine, Mom. I just need some sleep."', 'Where are you from?', 'Is that your bag?', 'Are you all right?', 'What is your favorite food?', 'C', 'Ayu menjawab "I''m fine", jadi pertanyaannya menanyakan keadaan: "Are you all right?"', 'aktif'),
(4, 1, 1, 'Tono touches his head. It hurts a lot. Tono has a ....', 'toothache', 'stomachache', 'headache', 'backache', 'C', 'Sakit di kepala disebut "headache".', 'aktif'),
(4, 1, 1, '"I have a fever." This means my body is ....', 'cold because of the rain', 'hot because I am sick', 'tired because I ran', 'happy because I won', 'B', '"Fever" artinya demam, yaitu suhu badan panas karena sakit.', 'aktif'),
(4, 1, 1, 'My tooth hurts. I have a ....', 'toothache', 'headache', 'earache', 'sore eye', 'A', 'Sakit gigi disebut "toothache".', 'aktif'),
(4, 1, 1, 'Rudi eats too much spicy food. Now his stomach hurts. He says, "...."', 'I have a sore throat.', 'I have a headache.', 'I have a broken leg.', 'I have a stomachache.', 'D', 'Perut yang sakit disebut "stomachache".', 'aktif'),
(4, 1, 1, 'Which sentence tells about a health condition?', 'I have a new pencil.', 'I have two brothers.', 'I have a big house.', 'I have a sore throat.', 'D', '"Sore throat" artinya sakit tenggorokan, yaitu informasi tentang kesehatan.', 'aktif'),
(4, 1, 1, 'Doctor: "How are you feeling?"
You are healthy now. You say, "...."', 'I have a terrible headache.', 'I feel very sick.', 'I feel much better, thank you.', 'My leg hurts a lot.', 'C', 'Kalau sudah sehat, kita menjawab "I feel much better", artinya "Aku merasa jauh lebih baik".', 'aktif'),
(4, 1, 2, 'Doctor: "What''s wrong with you?"
Sari: "...."', 'I live near the school.', 'I like to eat noodles.', 'I have a runny nose and I sneeze a lot.', 'I go to school by bus.', 'C', 'Sari menyebutkan keluhannya: hidung meler dan sering bersin.', 'aktif'),
(4, 1, 2, 'Read: "Hi, I''m Lia. I can''t go to school today. My body is hot and I feel weak."
What is wrong with Lia?', 'She has a toothache.', 'She has a broken arm.', 'She has a sore eye.', 'She has a fever.', 'D', 'Badan panas dan lemas adalah tanda demam (fever).', 'aktif'),
(4, 1, 2, 'Adit was sick yesterday. The teacher asks, "Why didn''t you come to school?"
Adit says, "...."', 'I was sick. I had a bad cough.', 'I was at the park all day.', 'I don''t like school.', 'I forgot my bag.', 'A', 'Adit memberi informasi tentang kondisi kesehatannya: ia sakit dan batuk parah.', 'aktif'),
(4, 1, 2, 'Nurse: "Where does it hurt?"
Boy: "...."', 'I''m ten years old.', 'I''m from Bandung.', 'It''s on the table.', 'My ear hurts. It''s very painful.', 'D', 'Perawat menanyakan bagian yang sakit, jadi jawabannya menyebut bagian tubuh: telinga.', 'aktif'),
(4, 1, 2, 'Mother: "How is your cough today?"
Ayu: "...."', 'It''s on Sunday, Mom.', 'It''s better now, Mom. I only cough a little.', 'It''s red and blue, Mom.', 'It''s my favorite song, Mom.', 'B', 'Ayu menjelaskan kondisi batuknya yang sudah membaik.', 'aktif'),
(4, 1, 2, 'Read: "Doni drank cold water all day. Now it hurts when he swallows."
Doni says, "...."', 'I have a sore throat.', 'I have a backache.', 'I have a sore eye.', 'I have a broken finger.', 'A', 'Sakit saat menelan adalah tanda sakit tenggorokan (sore throat).', 'aktif'),
(5, 3, 1, 'She ____ milk every morning.', 'drink', 'drinks', 'drinking', 'to drink', 'B', 'Subjek "she" memakai kata kerja + s/es: drink -> drinks.', 'aktif'),
(5, 3, 1, 'I ____ my hands before eating.', 'wash', 'washes', 'washing', 'to wash', 'A', 'Subjek "I" memakai kata kerja bentuk dasar tanpa -s: wash.', 'aktif'),
(5, 3, 1, 'My father ____ to the office by car.', 'go', 'going', 'goes', 'to go', 'C', '"My father" sama dengan "he", jadi go -> goes (kata berakhiran -o ditambah -es).', 'aktif'),
(5, 3, 1, 'They ____ football every Sunday.', 'play', 'plays', 'playing', 'to play', 'A', 'Subjek "they" memakai kata kerja bentuk dasar: play.', 'aktif'),
(5, 3, 1, 'The cat ____ a lot.', 'sleep', 'sleeping', 'to sleep', 'sleeps', 'D', '"The cat" sama dengan "it", jadi sleep -> sleeps.', 'aktif'),
(5, 3, 1, 'Which sentence is correct?', 'He brush his teeth twice a day.', 'He brushes his teeth twice a day.', 'He brushing his teeth twice a day.', 'He to brush his teeth twice a day.', 'B', 'Subjek "he" + kata kerja berakhiran -sh ditambah -es: brush -> brushes.', 'aktif'),
(5, 3, 2, 'Rina and her sister ____ vegetables every day.', 'eats', 'eat', 'eating', 'is eat', 'B', '"Rina and her sister" berarti dua orang (they), jadi kata kerjanya bentuk dasar: eat.', 'aktif'),
(5, 3, 2, 'Choose the correct sentence.', 'My mother cooks healthy food for us.', 'My mother cook healthy food for us.', 'My mother cooking healthy food for us.', 'My mother are cook healthy food for us.', 'A', '"My mother" sama dengan "she", jadi cook -> cooks.', 'aktif'),
(5, 3, 2, 'Budi ____ (study) English at night.', 'study', 'studys', 'studies', 'studying', 'C', 'Kata kerja berakhiran konsonan + y, y diganti -ies: study -> studies.', 'aktif'),
(5, 3, 2, 'Find the WRONG sentence.', 'We go to bed at nine o''clock.', 'He goes to bed at nine o''clock.', 'I go to bed at nine o''clock.', 'She go to bed at nine o''clock.', 'D', 'Subjek "she" harus memakai "goes", bukan "go".', 'aktif'),
(5, 3, 2, 'The doctor ____ the patients every morning.', 'check', 'checking', 'checks', 'are check', 'C', '"The doctor" sama dengan "he/she", jadi check -> checks.', 'aktif'),
(5, 3, 2, 'Every day, Andi ____ up at 5 a.m. He ____ exercise before school.', 'wake - do', 'wakes - does', 'wakes - do', 'wake - does', 'B', 'Subjek Andi/he: wake -> wakes, dan do -> does.', 'aktif'),
(6, 3, 1, '____ you drink water every day?', 'Do', 'Does', 'Is', 'Are', 'A', 'Kalimat tanya dengan subjek "you" memakai "Do".', 'aktif'),
(6, 3, 1, '____ she like vegetables?', 'Do', 'Does', 'Is', 'Are', 'B', 'Kalimat tanya dengan subjek "she" memakai "Does".', 'aktif'),
(6, 3, 1, 'I ____ eat candy before bed.', 'doesn''t', 'isn''t', 'don''t', 'not', 'C', 'Kalimat negatif dengan subjek "I" memakai "don''t" (do not).', 'aktif'),
(6, 3, 1, 'He ____ wash his hands before eating. That is not healthy.', 'don''t', 'isn''t', 'aren''t', 'doesn''t', 'D', 'Kalimat negatif dengan subjek "he" memakai "doesn''t" (does not).', 'aktif'),
(6, 3, 1, '"Do you exercise every day?" - "Yes, I ____."', 'does', 'am', 'do', 'is', 'C', 'Pertanyaan dengan "Do" dijawab dengan "Yes, I do".', 'aktif'),
(6, 3, 1, '"Does Rina sleep early?" - "No, she ____."', 'don''t', 'isn''t', 'not', 'doesn''t', 'D', 'Pertanyaan dengan "Does" dijawab dengan "No, she doesn''t".', 'aktif'),
(6, 3, 2, 'Change into a negative sentence: "My brother drinks soda every day."', 'My brother don''t drink soda every day.', 'My brother doesn''t drink soda every day.', 'My brother doesn''t drinks soda every day.', 'My brother not drink soda every day.', 'B', 'Pakai "doesn''t" untuk he/she/it, lalu kata kerja kembali ke bentuk dasar (drink, bukan drinks).', 'aktif'),
(6, 3, 2, 'Change into a question: "They eat fruit every day."', 'Does they eat fruit every day?', 'Do they eats fruit every day?', 'Are they eat fruit every day?', 'Do they eat fruit every day?', 'D', 'Subjek "they" memakai "Do", dan kata kerjanya tetap bentuk dasar: eat.', 'aktif'),
(6, 3, 2, 'Choose the correct question.', 'Does your sister brush her teeth every night?', 'Do your sister brush her teeth every night?', 'Does your sister brushes her teeth every night?', 'Is your sister brush her teeth every night?', 'A', '"Your sister" = she, jadi memakai "Does", dan kata kerjanya bentuk dasar: brush.', 'aktif'),
(6, 3, 2, 'Dina: "____ your father smoke?"
Raka: "No, he doesn''t. He lives a healthy life."', 'Does', 'Do', 'Is', 'Are', 'A', '"Your father" = he, jadi kalimat tanya memakai "Does".', 'aktif'),
(6, 3, 2, 'Find the correct sentence.', 'We doesn''t eat junk food every day.', 'We not eat junk food every day.', 'We don''t eat junk food every day.', 'We don''t eats junk food every day.', 'C', 'Subjek "we" memakai "don''t", dan kata kerjanya bentuk dasar: eat.', 'aktif'),
(6, 3, 2, '"Do the students wash their hands before lunch?" - "Yes, ____."', 'they does', 'he does', 'they are', 'they do', 'D', '"The students" = they, dan pertanyaan dengan "Do" dijawab "Yes, they do".', 'aktif');
