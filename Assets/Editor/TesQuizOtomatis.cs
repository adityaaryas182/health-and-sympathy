using System;
using System.Collections;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using System.Text;
using UnityEditor;
using UnityEngine;
using UnityEngine.Networking;
using UnityEngine.SceneManagement;
using UnityEngine.UI;

/// <summary>
/// Tes otomatis sesi trap BKT (hanya Editor, tidak ikut build).
///
/// Cara pakai: buka scene Level 01/02/03 -> Play -> menu Tools > Tes Quiz Otomatis -> Jalankan Tes.
/// Tes membuka trap 1-5 berurutan, menjawab sesuai pola (benar/salah/timeout) dengan menekan
/// tombol jawaban sungguhan, lalu memeriksa:
///   - tombol A-D terkirim sebagai huruf yang benar,
///   - kunci di database sama dengan bank_soal.txt,
///   - P(L) dari server sama dengan hitungan BKT ulang di sini,
///   - aturan berhenti (min/max soal, mastery), kategori hasil trap, dan nyawa,
///   - skill & tingkat soal sesuai nomor trap, remedial memilih skill P(L) terendah,
///   - soal tidak langsung diulang, skor benar/salah ikut bertambah.
/// Semua baris ditulis ke Console dengan awalan [TesQuiz] dan ke Logs/TesQuiz/*.txt.
///
/// PERHATIAN: jawaban tes BENAR-BENAR tersimpan ke database untuk id_siswa yang dipakai.
/// </summary>
public class TesQuizOtomatis : EditorWindow
{
    public enum Pola
    {
        SemuaBenar,
        SemuaSalah,
        BenarSalahSelang,
        TimeoutLaluSalah
    }

    [SerializeField] private int idSiswa = 23;
    [SerializeField] private bool percepat = true;
    [SerializeField] private Pola[] polaTrap = DefaultPola();
    [SerializeField] private bool tampilParam;
    [SerializeField] private float pLearn = 0.10f;
    [SerializeField] private float pGuess = 0.25f;
    [SerializeField] private float pSlip = 0.10f;

    private Runner runner;
    private Vector2 scroll;
    private GUIStyle gayaLog;

    [MenuItem("Tools/Tes Quiz Otomatis")]
    private static void Buka()
    {
        GetWindow<TesQuizOtomatis>("Tes Quiz Otomatis");
    }

    private static Pola[] DefaultPola()
    {
        return new[] { Pola.SemuaBenar, Pola.BenarSalahSelang, Pola.TimeoutLaluSalah, Pola.SemuaBenar, Pola.SemuaBenar };
    }

    private void OnEnable()
    {
        if (polaTrap == null || polaTrap.Length != 5)
        {
            polaTrap = DefaultPola();
        }
    }

    private void Update()
    {
        if (runner == null || !runner.Berjalan)
        {
            return;
        }

        if (!EditorApplication.isPlaying || QuizManager.Instance == null)
        {
            runner.Batalkan(!EditorApplication.isPlaying ? "Play mode dihentikan." : "QuizManager hilang (scene dimuat ulang?).");
        }

        Repaint();
    }

    private void OnGUI()
    {
        if (gayaLog == null)
        {
            gayaLog = new GUIStyle(EditorStyles.label) { richText = true, wordWrap = true };
        }

        EditorGUILayout.LabelField("Tes otomatis sesi trap BKT", EditorStyles.boldLabel);
        EditorGUILayout.HelpBox(
            "1. Buka scene Level 01 / 02 / 03\n2. Tekan Play\n3. Klik \"Jalankan Tes\"\n\n" +
            "Jawaban tes TERSIMPAN ke database untuk id_siswa yang dipakai.",
            MessageType.Info);

        bool sedangJalan = runner != null && runner.Berjalan;

        using (new EditorGUI.DisabledScope(sedangJalan))
        {
            idSiswa = EditorGUILayout.IntField(
                new GUIContent("id_siswa", "Dipakai jika belum login lewat scene Login."), idSiswa);
            percepat = EditorGUILayout.Toggle(
                new GUIContent("Percepat", "Timer soal 4 detik, jeda umpan balik/pembahasan/hasil dipersingkat."), percepat);

            for (int i = 0; i < 5; i++)
            {
                polaTrap[i] = (Pola)EditorGUILayout.EnumPopup("Trap " + (i + 1) + (i == 4 ? " (remedial)" : ""), polaTrap[i]);
            }

            tampilParam = EditorGUILayout.Foldout(tampilParam, "Parameter BKT untuk cek ulang (sama dengan tabel skill)");
            if (tampilParam)
            {
                EditorGUI.indentLevel++;
                pLearn = EditorGUILayout.FloatField("P(T) learn", pLearn);
                pGuess = EditorGUILayout.FloatField("P(G) guess", pGuess);
                pSlip = EditorGUILayout.FloatField("P(S) slip", pSlip);
                EditorGUI.indentLevel--;
            }
        }

        EditorGUILayout.Space();

        if (!EditorApplication.isPlaying)
        {
            EditorGUILayout.HelpBox("Tekan Play dulu (di scene Level 01/02/03).", MessageType.Warning);
        }
        else if (QuizManager.Instance == null)
        {
            EditorGUILayout.HelpBox("Scene ini tidak punya QuizManager. Buka Level 01/02/03.", MessageType.Warning);
        }

        bool bisaMulai = EditorApplication.isPlaying && QuizManager.Instance != null && !sedangJalan;

        using (new EditorGUILayout.HorizontalScope())
        {
            using (new EditorGUI.DisabledScope(!bisaMulai))
            {
                if (GUILayout.Button("Jalankan Tes", GUILayout.Height(28)))
                {
                    Mulai();
                }
            }

            using (new EditorGUI.DisabledScope(!sedangJalan))
            {
                if (GUILayout.Button("Hentikan", GUILayout.Height(28), GUILayout.Width(90)))
                {
                    runner.Batalkan("Dihentikan manual.");
                }
            }
        }

        if (runner == null)
        {
            return;
        }

        EditorGUILayout.Space();
        string status = runner.Berjalan ? "BERJALAN..." : (runner.Gagal == 0 ? "SELESAI - SEMUA LULUS" : "SELESAI - ADA YANG GAGAL");
        EditorGUILayout.LabelField(
            status + "   |   Lulus: " + runner.Lulus + "   Gagal: " + runner.Gagal + "   Peringatan: " + runner.Peringatan,
            EditorStyles.boldLabel);

        if (!string.IsNullOrEmpty(runner.FileLaporan) && GUILayout.Button("Buka folder laporan"))
        {
            EditorUtility.RevealInFinder(runner.FileLaporan);
        }

        scroll = EditorGUILayout.BeginScrollView(scroll);
        foreach (string baris in runner.BarisTampil)
        {
            EditorGUILayout.LabelField(baris, gayaLog);
        }
        EditorGUILayout.EndScrollView();
    }

    private void Mulai()
    {
        QuizManager qm = QuizManager.Instance;
        runner = new Runner(qm, new Setelan
        {
            idSiswa = idSiswa,
            percepat = percepat,
            pola = (Pola[])polaTrap.Clone(),
            pLearn = pLearn,
            pGuess = pGuess,
            pSlip = pSlip
        });
        runner.Handle = qm.StartCoroutine(runner.Jalankan());
    }

    // ======================================================================

    private class Setelan
    {
        public int idSiswa;
        public bool percepat;
        public Pola[] pola;
        public float pLearn, pGuess, pSlip;
    }

    private enum Niat
    {
        Benar,
        Salah,
        Timeout
    }

    private class Runner
    {
        private const string Huruf = "ABCD";
        private const float BatasTunggu = 45f;

        public bool Berjalan;
        public int Lulus, Gagal, Peringatan;
        public string FileLaporan;
        public Coroutine Handle;
        public readonly List<string> BarisTampil = new List<string>();

        private readonly List<string> barisFile = new List<string>();
        private readonly QuizManager qm;
        private readonly Setelan s;
        private PlayerHealth hp;
        private Dictionary<string, List<string>> kunci = new Dictionary<string, List<string>>();
        private readonly Dictionary<int, float> pTerakhirSkill = new Dictionary<int, float>();
        private readonly Dictionary<int, int> skillPerTrap = new Dictionary<int, int>();
        private int idSoalSebelumnya;
        private bool hentikan;

        // nilai asli QuizManager (dikembalikan setelah tes)
        private float asliDurasi, asliFeedback, asliPembahasan, asliHasil;

        public Runner(QuizManager qm, Setelan s)
        {
            this.qm = qm;
            this.s = s;
        }

        // ---------------- laporan ----------------

        private void Tulis(string label, string warna, string pesan, LogType tipe)
        {
            string polos = (label.Length > 0 ? "[" + label + "] " : "") + pesan;
            barisFile.Add(polos);
            BarisTampil.Add(warna != null ? "<color=" + warna + ">" + polos + "</color>" : polos);

            string log = "[TesQuiz] " + polos;
            if (tipe == LogType.Warning) Debug.LogWarning(log);
            else Debug.Log(log);
        }

        private void Info(string p) { Tulis("", null, p, LogType.Log); }
        private void Judul(string p) { Tulis("", null, "===== " + p + " =====", LogType.Log); }
        private void Ok(string p) { Lulus++; Tulis("OK", "#4CAF50", p, LogType.Log); }
        // Pakai LogWarning (bukan LogError) supaya "Error Pause" di Console tidak menjeda Play.
        private void Salah(string p) { Gagal++; Tulis("GAGAL", "#F44336", p, LogType.Warning); }
        private void Awas(string p) { Peringatan++; Tulis("PERINGATAN", "#FFA000", p, LogType.Warning); }

        private void Cek(bool kondisi, string ok, string gagal)
        {
            if (kondisi) Ok(ok);
            else Salah(gagal);
        }

        public void Batalkan(string alasan)
        {
            if (!Berjalan) return;

            if (Handle != null && qm != null)
            {
                qm.StopCoroutine(Handle);
            }

            Salah("Tes dibatalkan: " + alasan);
            Akhiri();
        }

        private void Akhiri()
        {
            if (!Berjalan) return;
            Berjalan = false;

            if (qm != null && s.percepat)
            {
                qm.quizDuration = asliDurasi;
                qm.feedbackDuration = asliFeedback;
                qm.pembahasanDuration = asliPembahasan;
                qm.hasilTrapDuration = asliHasil;
            }

            Judul("RINGKASAN: Lulus " + Lulus + " | Gagal " + Gagal + " | Peringatan " + Peringatan);

            try
            {
                string folder = Path.GetFullPath(Path.Combine(Application.dataPath, "..", "Logs", "TesQuiz"));
                Directory.CreateDirectory(folder);
                FileLaporan = Path.Combine(folder, "tes_quiz_" + DateTime.Now.ToString("yyyyMMdd_HHmmss") + ".txt");
                File.WriteAllLines(FileLaporan, barisFile, new UTF8Encoding(false));
                Info("Laporan: " + FileLaporan);
            }
            catch (Exception e)
            {
                Debug.LogWarning("[TesQuiz] Gagal menulis laporan: " + e.Message);
            }
        }

        // ---------------- alur utama ----------------

        public IEnumerator Jalankan()
        {
            Berjalan = true;
            Judul("TES QUIZ OTOMATIS " + DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss"));
            Info("Scene: " + SceneManager.GetActiveScene().name + " | Unity " + Application.unityVersion);

            hp = UnityEngine.Object.FindFirstObjectByType<PlayerHealth>();
            if (hp == null) Awas("PlayerHealth tidak ditemukan; cek nyawa dilewati.");

            if (qm.SesiBerjalan)
            {
                Salah("Masih ada sesi trap yang berjalan. Selesaikan dulu atau restart Play.");
                Akhiri();
                yield break;
            }

            if (qm.IdSiswa <= 0)
            {
                qm.SetIdSiswaUntukTes(s.idSiswa);
                Info("Belum login -> memakai id_siswa " + qm.IdSiswa + " dari jendela tes.");
            }
            else
            {
                Info("Memakai id_siswa hasil login: " + qm.IdSiswa);
            }

            if (qm.LevelGame <= 0)
            {
                Salah("Level game tidak diketahui (nama scene harus \"Level 01\" dst).");
                Akhiri();
                yield break;
            }

            Info("Level game: " + qm.LevelGame + " | Parameter cek ulang BKT: T=" + s.pLearn + " G=" + s.pGuess + " S=" + s.pSlip);

            string pathKunci;
            kunci = BacaKunci(out pathKunci);
            if (kunci.Count == 0) Awas("Kunci jawaban tidak terbaca dari " + pathKunci + ". Pola benar/salah tidak bisa dijamin.");
            else Info("Kunci jawaban dibaca dari bank_soal.txt: " + kunci.Count + " soal.");

            // Cek server dulu supaya tidak terjebak di "Mencoba lagi..." tanpa batas.
            bool serverOk = false;
            yield return CekServer(r => serverOk = r);
            if (!serverOk)
            {
                Akhiri();
                yield break;
            }

            asliDurasi = qm.quizDuration;
            asliFeedback = qm.feedbackDuration;
            asliPembahasan = qm.pembahasanDuration;
            asliHasil = qm.hasilTrapDuration;

            if (s.percepat)
            {
                qm.quizDuration = 4f;
                qm.feedbackDuration = 0.3f;
                qm.pembahasanDuration = 0.6f;
                qm.hasilTrapDuration = 0.8f;
            }

            if (qm.answerButtons == null || qm.answerButtons.Length != 4 || qm.answerButtons.Any(b => b == null))
            {
                Salah("Answer Buttons di QuizManager harus berisi 4 tombol.");
                Akhiri();
                yield break;
            }

            QuizTrigger[] semuaTrap = UnityEngine.Object.FindObjectsByType<QuizTrigger>(FindObjectsInactive.Include, FindObjectsSortMode.None);
            Cek(semuaTrap.Length == 5, "Scene punya 5 trap.", "Scene punya " + semuaTrap.Length + " trap (harusnya 5).");

            for (int n = 1; n <= 5 && !hentikan; n++)
            {
                QuizTrigger trap = semuaTrap.FirstOrDefault(t => t.nomorTrap == n);

                if (trap == null)
                {
                    Salah("Trap nomor " + n + " tidak ada di scene.");
                    continue;
                }

                if (!trap.gameObject.activeInHierarchy)
                {
                    Awas("Trap " + n + " sudah tidak aktif (sudah dimainkan?). Dilewati. Restart Play untuk tes penuh.");
                    continue;
                }

                yield return JalankanTrap(trap, s.pola[n - 1]);

                if (hp != null && hp.IsDead)
                {
                    Info("Nyawa habis -> level akan dimuat ulang. Tes berhenti di sini.");
                    break;
                }
            }

            // Skill per trap konsisten?
            if (skillPerTrap.ContainsKey(1) && skillPerTrap.ContainsKey(2))
                Cek(skillPerTrap[1] == skillPerTrap[2], "Trap 1 & 2 memakai skill yang sama.", "Trap 1 & 2 memakai skill berbeda.");
            if (skillPerTrap.ContainsKey(3) && skillPerTrap.ContainsKey(4))
                Cek(skillPerTrap[3] == skillPerTrap[4], "Trap 3 & 4 memakai skill yang sama.", "Trap 3 & 4 memakai skill berbeda.");
            if (skillPerTrap.ContainsKey(1) && skillPerTrap.ContainsKey(3))
                Cek(skillPerTrap[1] < skillPerTrap[3], "Trap 1-2 = skill pertama, trap 3-4 = skill kedua.", "Urutan skill trap 1-2 vs 3-4 terbalik/sama.");

            Akhiri();
        }

        private IEnumerator JalankanTrap(QuizTrigger trap, Pola pola)
        {
            int n = trap.nomorTrap;
            Judul("TRAP " + n + " | pola: " + pola);

            int nyawaAwal = hp != null ? hp.CurrentLives : -1;
            ScoreManager sm = ScoreManager.Instance;
            int benarAwal = sm != null ? sm.GetJumlahBenar() : 0;
            int salahAwal = sm != null ? sm.GetJumlahSalah() : 0;
            int tampilAwal = qm.JumlahSoalDitampilkan;

            if (!qm.OpenQuiz(trap))
            {
                Salah("OpenQuiz menolak trap " + n + " (sesi lain masih berjalan?).");
                hentikan = true;
                yield break;
            }

            bool ok = false;
            yield return Tunggu(() => (qm.JumlahSoalDitampilkan > tampilAwal && qm.MenungguJawaban) || !qm.SesiBerjalan, BatasTunggu, r => ok = r);

            if (!ok || !qm.SesiBerjalan)
            {
                Salah("Soal pertama trap " + n + " tidak muncul. Pesan di panel: \"" + TeksPanel() + "\"");
                hentikan = true;
                yield break;
            }

            TrapInfo info = qm.TrapAktif;
            if (info == null)
            {
                Salah("Respons get_soal tidak berisi data trap.");
                hentikan = true;
                yield break;
            }

            Info("Skill: " + info.nama_skill + " (id " + info.id_skill + ") | tingkat " + info.tingkat +
                 " | remedial: " + info.remedial + " | P(L) awal: " + info.p_know.ToString("0.0000") +
                 " | min/max soal: " + info.min_soal + "/" + info.max_soal);

            Cek(info.remedial == (n == 5), "Status remedial sesuai nomor trap.", "Status remedial tidak sesuai (trap " + n + ", remedial=" + info.remedial + ").");

            if (n < 5)
            {
                int tingkatHarap = (n == 1 || n == 3) ? 1 : 2;
                Cek(info.tingkat == tingkatHarap, "Tingkat trap = " + tingkatHarap + ".", "Tingkat trap " + info.tingkat + ", harusnya " + tingkatHarap + ".");
            }
            else
            {
                CekRemedial(info);
            }

            skillPerTrap[n] = info.id_skill;

            float prior = info.p_know;
            int dijawab = 0, jmlBenar = 0, jmlSalah = 0;
            bool mastery = false;
            float pAkhir = prior;
            bool aturanOk = true;
            var idDalamSesi = new HashSet<int>();
            var jawabanSesi = new List<bool>();
            float nilaiMenguasai = info.nilai_menguasai > 0f ? info.nilai_menguasai : 80f;
            float nilaiCukup = info.nilai_cukup > 0f ? info.nilai_cukup : 60f;
            string kategori = QuizManager.KategoriBelum;

            while (true)
            {
                SoalData soal = qm.SoalAktif;
                Niat niat = NiatUntuk(pola, dijawab);

                // --- cek soal ---
                if (soal.id_skill != info.id_skill)
                    Salah("Soal id " + soal.id_soal + " dari skill " + soal.id_skill + ", bukan skill trap " + info.id_skill + ".");
                if (info.tingkat > 0 && soal.tingkat != info.tingkat)
                    Awas("Soal id " + soal.id_soal + " tingkat " + soal.tingkat + " (trap minta " + info.tingkat + "); kemungkinan soal tingkat ini habis.");
                if (soal.id_soal == idSoalSebelumnya)
                    Salah("Soal id " + soal.id_soal + " langsung diulang.");
                else if (idDalamSesi.Contains(soal.id_soal))
                    Awas("Soal id " + soal.id_soal + " muncul lagi di sesi yang sama (bank soal skill ini mulai habis).");
                idDalamSesi.Add(soal.id_soal);

                // --- pilih tombol ---
                int idxKunci = CariKunci(soal);
                int idxKlik;

                if (niat == Niat.Timeout)
                {
                    idxKlik = -1;
                }
                else if (idxKunci < 0)
                {
                    Awas("Kunci soal id " + soal.id_soal + " tidak ditemukan di bank_soal.txt; menekan A.");
                    idxKlik = 0;
                }
                else
                {
                    idxKlik = niat == Niat.Benar ? idxKunci : (idxKunci + 1) % 4;
                }

                yield return new WaitForSecondsRealtime(0.25f);

                int simpanAwal = qm.JumlahJawabanTersimpan;
                if (idxKlik >= 0)
                {
                    qm.answerButtons[idxKlik].onClick.Invoke();
                }

                yield return Tunggu(() => qm.JumlahJawabanTersimpan > simpanAwal, qm.quizDuration + BatasTunggu, r => ok = r);
                if (!ok)
                {
                    Salah("Jawaban soal id " + soal.id_soal + " tidak tersimpan. Pesan di panel: \"" + TeksPanel() + "\"");
                    hentikan = true;
                    yield break;
                }

                SubmitJawabanResponse h = qm.HasilTerakhir;
                dijawab++;

                string hurufKlik = idxKlik >= 0 ? Huruf[idxKlik].ToString() : "-";
                Info("Soal " + dijawab + ": id " + soal.id_soal + " (tingkat " + soal.tingkat + ") | klik " + hurufKlik +
                     " [" + niat + "] | kunci " + h.jawaban_benar + " | " + (h.benar ? "BENAR" : "SALAH") +
                     " | P(L) " + prior.ToString("0.0000") + " -> " + h.p_know.ToString("0.0000") +
                     (h.mastery_tercapai ? " (mastery)" : ""));

                // --- cek jawaban ---
                Cek(h.jawaban_siswa == hurufKlik,
                    "Tombol " + hurufKlik + " tercatat sebagai " + h.jawaban_siswa + ".",
                    "Tombol " + hurufKlik + " tercatat sebagai " + h.jawaban_siswa + " di server!");

                if (idxKunci >= 0)
                {
                    Cek(h.jawaban_benar == Huruf[idxKunci].ToString(),
                        "Kunci DB (" + h.jawaban_benar + ") = bank_soal.txt.",
                        "Kunci DB " + h.jawaban_benar + " beda dengan bank_soal.txt (" + Huruf[idxKunci] + ").");
                }

                if (niat == Niat.Timeout || idxKunci >= 0)
                {
                    bool harapBenar = niat == Niat.Benar;
                    Cek(h.benar == harapBenar,
                        "Penilaian server sesuai (" + (harapBenar ? "benar" : "salah") + ").",
                        "Server menilai " + (h.benar ? "benar" : "salah") + ", harusnya " + (harapBenar ? "benar" : "salah") + ".");
                }

                float pHarap = HitungBKT(prior, h.benar);
                Cek(Mathf.Abs(pHarap - h.p_know) <= 0.0006f,
                    "P(L) server = hitungan ulang (" + pHarap.ToString("0.0000") + ").",
                    "P(L) server " + h.p_know.ToString("0.0000") + " beda dengan hitungan ulang " + pHarap.ToString("0.0000") +
                    " (parameter skill di DB beda dari jendela tes?).");

                Cek(h.mastery_tercapai == (h.p_know >= info.batas_mastery),
                    "Flag mastery konsisten.",
                    "Flag mastery " + h.mastery_tercapai + " tidak cocok dengan P(L) " + h.p_know + ".");

                if (h.benar) jmlBenar++;
                else jmlSalah++;

                prior = h.p_know;
                pAkhir = h.p_know;
                jawabanSesi.Add(h.benar);
                // Kategori trap = P(L) dan nilai (% benar di trap ini), lihat config/bkt.php.
                kategori = QuizManager.KategoriTrap(h.p_know, jawabanSesi, info.batas_mastery, info.batas_cukup,
                                                    nilaiMenguasai, nilaiCukup);
                mastery = kategori == QuizManager.KategoriMenguasai;
                idSoalSebelumnya = soal.id_soal;

                // --- aturan berhenti ---
                bool harusBerhenti = (dijawab >= info.min_soal && mastery) || dijawab >= info.max_soal;
                int tampilSebelum = qm.JumlahSoalDitampilkan;

                yield return Tunggu(
                    () => (qm.JumlahSoalDitampilkan > tampilSebelum && qm.MenungguJawaban) || !qm.SesiBerjalan || (hp != null && hp.IsDead),
                    BatasTunggu, r => ok = r);

                if (!ok)
                {
                    Salah("Setelah soal " + dijawab + " tidak ada soal baru dan sesi tidak selesai. Pesan: \"" + TeksPanel() + "\"");
                    hentikan = true;
                    yield break;
                }

                bool lanjut = qm.SesiBerjalan && qm.JumlahSoalDitampilkan > tampilSebelum;

                if (lanjut && harusBerhenti)
                {
                    aturanOk = false;
                    Salah("Sesi lanjut ke soal berikutnya padahal harusnya berhenti setelah soal " + dijawab + ".");
                }
                else if (!lanjut && !harusBerhenti)
                {
                    aturanOk = false;
                    Salah("Sesi berhenti setelah soal " + dijawab + " padahal belum mastery dan belum " + info.max_soal + " soal.");
                }

                if (!lanjut)
                {
                    break;
                }
            }

            // --- hasil trap ---
            Info("Hasil trap " + n + ": " + dijawab + " soal | P(L) akhir " + pAkhir.ToString("0.0000") + " -> " + kategori);
            if (aturanOk)
            {
                Ok("Aturan berhenti sesuai (" + dijawab + " soal, min " + info.min_soal + " / max " + info.max_soal + ").");
            }

            pTerakhirSkill[info.id_skill] = pAkhir;

            if (hp != null)
            {
                int harapDelta = kategori == "Belum Menguasai" ? -1 : 0;
                int delta = hp.CurrentLives - nyawaAwal;
                Cek(delta == harapDelta,
                    "Nyawa " + nyawaAwal + " -> " + hp.CurrentLives + " (sesuai kategori).",
                    "Nyawa " + nyawaAwal + " -> " + hp.CurrentLives + ", harusnya berubah " + harapDelta + ".");
            }

            if (sm != null)
            {
                Cek(sm.GetJumlahBenar() - benarAwal == jmlBenar && sm.GetJumlahSalah() - salahAwal == jmlSalah,
                    "ScoreManager mencatat " + jmlBenar + " benar / " + jmlSalah + " salah.",
                    "ScoreManager mencatat " + (sm.GetJumlahBenar() - benarAwal) + " benar / " + (sm.GetJumlahSalah() - salahAwal) +
                    " salah, harusnya " + jmlBenar + " / " + jmlSalah + ".");
            }

            if (hp == null || !hp.IsDead)
            {
                Cek(!trap.gameObject.activeSelf, "Trap " + n + " nonaktif setelah selesai.", "Trap " + n + " masih aktif setelah selesai.");
                Cek(Mathf.Approximately(Time.timeScale, 1f), "Game tidak dijeda lagi (timeScale 1).", "timeScale masih " + Time.timeScale + " setelah trap selesai.");
            }
        }

        private void CekRemedial(TrapInfo info)
        {
            // Hanya bisa dicek jika kedua skill level ini sudah dimainkan di tes ini.
            var skillLevel = skillPerTrap.Where(kv => kv.Key <= 4).Select(kv => kv.Value).Distinct().ToList();
            if (skillLevel.Count < 2 || !skillLevel.All(pTerakhirSkill.ContainsKey))
            {
                Info("Cek pilihan skill remedial dilewati (trap 1-4 belum lengkap di tes ini).");
                return;
            }

            int harap = skillLevel.OrderBy(id => pTerakhirSkill[id]).ThenBy(id => id).First();
            Cek(info.id_skill == harap,
                "Remedial memilih skill P(L) terendah (id " + harap + ", P(L) " + pTerakhirSkill[harap].ToString("0.0000") + ").",
                "Remedial memilih skill " + info.id_skill + ", harusnya " + harap + " (P(L) terendah).");
            Cek(Mathf.Abs(info.p_know - pTerakhirSkill[harap]) <= 0.0006f,
                "P(L) awal remedial = P(L) akhir skill itu sebelumnya.",
                "P(L) awal remedial " + info.p_know + " beda dengan P(L) akhir skill itu (" + pTerakhirSkill[harap] + ").");
        }

        // ---------------- server ----------------

        private IEnumerator CekServer(Action<bool> hasil)
        {
            string url = qm.getSoalUrl + (qm.getSoalUrl.Contains("?") ? "&" : "?") +
                         "mode=trap&id_siswa=" + qm.IdSiswa + "&level=" + qm.LevelGame + "&trap=1";

            using (UnityWebRequest req = UnityWebRequest.Get(url))
            {
                req.timeout = 5;
                yield return req.SendWebRequest();

                if (req.result != UnityWebRequest.Result.Success)
                {
                    Salah("Server tidak bisa dihubungi (" + req.error + "). Nyalakan Laragon (Apache + MySQL). URL: " + url);
                    hasil(false);
                    yield break;
                }

                SoalResponse r = null;
                try { r = JsonUtility.FromJson<SoalResponse>(req.downloadHandler.text); }
                catch (Exception) { /* ditangani di bawah */ }

                if (r == null || !r.success)
                {
                    Salah("Server menolak: " + (r != null ? r.message : req.downloadHandler.text));
                    hasil(false);
                    yield break;
                }

                Ok("Server & database siap (get_soal OK untuk id_siswa " + qm.IdSiswa + ").");
                hasil(true);
            }
        }

        // ---------------- bantuan ----------------

        private IEnumerator Tunggu(Func<bool> kondisi, float batasDetik, Action<bool> hasil)
        {
            float mulai = Time.realtimeSinceStartup;

            while (!kondisi())
            {
                // Jika scene memakai tombol "Lanjut" untuk pembahasan, tekan otomatis.
                if (qm.lanjutButton != null && qm.lanjutButton.gameObject.activeInHierarchy)
                {
                    qm.lanjutButton.onClick.Invoke();
                }

                if (Time.realtimeSinceStartup - mulai > batasDetik)
                {
                    hasil(false);
                    yield break;
                }

                yield return null;
            }

            hasil(true);
        }

        private float HitungBKT(float pKnow, bool benar)
        {
            double p = pKnow, t = s.pLearn, g = s.pGuess, sl = s.pSlip;
            double atas = benar ? p * (1 - sl) : p * sl;
            double bawah = benar ? p * (1 - sl) + (1 - p) * g : p * sl + (1 - p) * (1 - g);
            double posterior = bawah == 0 ? p : atas / bawah;
            return (float)Math.Round(posterior + (1 - posterior) * t, 4, MidpointRounding.AwayFromZero);
        }

        private static Niat NiatUntuk(Pola pola, int indeks)
        {
            switch (pola)
            {
                case Pola.SemuaSalah: return Niat.Salah;
                case Pola.BenarSalahSelang: return indeks % 2 == 0 ? Niat.Benar : Niat.Salah;
                case Pola.TimeoutLaluSalah: return indeks == 0 ? Niat.Timeout : Niat.Salah;
                default: return Niat.Benar;
            }
        }

        private string TeksPanel()
        {
            return qm.questionText != null ? qm.questionText.text.Replace("\n", " ") : "";
        }

        private int CariKunci(SoalData soal)
        {
            List<string> daftarBenar;
            if (!kunci.TryGetValue(Norm(soal.pertanyaan), out daftarBenar))
            {
                return -1;
            }

            string[] pilihan = { soal.pilihan_a, soal.pilihan_b, soal.pilihan_c, soal.pilihan_d };
            int ketemu = -1;

            for (int i = 0; i < 4; i++)
            {
                if (daftarBenar.Contains(Norm(pilihan[i])))
                {
                    if (ketemu >= 0) return -1; // ambigu
                    ketemu = i;
                }
            }

            return ketemu;
        }

        private static string Norm(string teks)
        {
            return (teks ?? "").Replace("\r\n", "\n").Trim();
        }

        /// <summary>Membaca Database/bank_soal/bank_soal.txt (format sama dengan generate.php).</summary>
        private static Dictionary<string, List<string>> BacaKunci(out string path)
        {
            path = Path.GetFullPath(Path.Combine(Application.dataPath, "..", "Database", "bank_soal", "bank_soal.txt"));
            var hasil = new Dictionary<string, List<string>>();

            if (!File.Exists(path))
            {
                return hasil;
            }

            string q = null;
            string benar = null;

            Action simpan = () =>
            {
                if (q != null && benar != null)
                {
                    string k = Norm(q);
                    if (!hasil.ContainsKey(k)) hasil[k] = new List<string>();
                    hasil[k].Add(Norm(benar));
                }
                q = null;
                benar = null;
            };

            foreach (string mentah in File.ReadAllLines(path, Encoding.UTF8))
            {
                string baris = mentah.TrimEnd();

                if (baris.StartsWith("## "))
                {
                    simpan();
                    continue;
                }

                if (baris.Length == 0 || baris[0] == '#')
                {
                    continue;
                }

                if (baris.StartsWith("Q:"))
                {
                    string isi = baris.Substring(2).Trim();
                    if (q != null && benar != null) simpan();
                    q = q == null ? isi : q + "\n" + isi;
                }
                else if (baris.StartsWith("+ "))
                {
                    benar = baris.Substring(2).Trim();
                }
            }

            simpan();
            return hasil;
        }
    }
}
