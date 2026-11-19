using System;
using System.Collections;
using System.Collections.Generic;
using System.Text;
using System.Text.RegularExpressions;
using TMPro;
using UnityEngine;
using UnityEngine.Networking;
using UnityEngine.SceneManagement;
using UnityEngine.UI;

[Serializable]
public class SoalData
{
    public int id_soal;
    public int id_skill;
    public int tingkat;
    public string pertanyaan;
    public string pilihan_a;
    public string pilihan_b;
    public string pilihan_c;
    public string pilihan_d;
    // Kunci jawaban & pembahasan sengaja tidak dikirim get_soal; penilaian ada di server.
}

[Serializable]
public class TrapInfo
{
    public int level;
    public int nomor_trap;
    public bool remedial;
    public int id_skill;
    public string nama_skill;
    public int tingkat;
    public float p_know;
    public int min_soal;
    public int max_soal;
    public float nilai_menguasai;
    public float nilai_cukup;
    public float batas_mastery;
    public float batas_cukup;
}

[Serializable]
public class SoalResponse
{
    public bool success;
    public string message;
    public int total;
    public SoalData[] data;
    public TrapInfo trap;
}

[Serializable]
public class SubmitJawabanRequest
{
    public int id_siswa;
    public int id_soal;
    public string jawaban_siswa;
    public string kode_kirim;
}

[Serializable]
public class SubmitJawabanResponse
{
    public bool success;
    public string message;
    public bool duplikat;
    public int id_siswa;
    public int id_soal;
    public int id_skill;
    public string jawaban_siswa;
    public string jawaban_benar;
    public bool benar;
    public float p_know;
    public bool mastery_tercapai;
    public string pembahasan;
}

/// <summary>
/// Menjalankan sesi trap berbasis BKT (mastery learning):
/// satu trap = beberapa soal dari satu skill. Sesi selesai jika trap sudah
/// "Menguasai" (minimal min_soal soal) atau sudah max_soal soal.
/// Kategori (sama dengan kategoriPenguasaan() di config/bkt.php), nilai = % benar di trap:
///   Menguasai       : P(L) >= batas_mastery dan nilai >= nilai_menguasai
///   Cukup Menguasai : P(L) >= batas_cukup   dan nilai >= nilai_cukup   (nyawa tetap)
///   Belum Menguasai : selain itu                                        (nyawa -1)
/// Pemilihan soal, nilai P(L), dan semua batas diatur backend (config/bkt.php).
/// </summary>
public class QuizManager : MonoBehaviour
{
    public static QuizManager Instance;

    [Header("Backend")]
    public string getSoalUrl = "http://localhost/backend/api/get_soal.php";
    public string submitJawabanUrl = "http://localhost/backend/api/submit_jawaban.php";

    [Tooltip("Level game (= materi). 0 = otomatis dari nama scene, misal \"Level 02\" -> 2.")]
    [SerializeField] private int levelGame = 0;

    [Tooltip("HANYA di Unity Editor: id_siswa yang dipakai jika belum login. 0 = wajib login.")]
    [SerializeField] private int idSiswaTesEditor = 0;

    [Tooltip("Batas waktu satu request ke server (detik).")]
    [SerializeField] private int requestTimeout = 10;

    [Tooltip("Jeda sebelum mencoba lagi saat koneksi gagal (detik, realtime).")]
    [SerializeField] private float jedaCobaUlang = 2f;

    [Header("UI Quiz")]
    public GameObject quizPanel;
    [SerializeField] private GameObject controlPanel;
    [SerializeField] private MobileController mobileController; // Referensi untuk mereset movement
    public TMP_Text questionText;
    public TMP_Text timerText;
    public Button[] answerButtons;
    public Image feedbackImage;
    public Sprite correctSprite;
    public Sprite wrongSprite;

    [Header("Pembahasan (opsional)")]
    [Tooltip("Teks khusus pembahasan. Kosong = pembahasan ditampilkan di teks pertanyaan.")]
    public TMP_Text pembahasanText;
    [Tooltip("Tombol \"Lanjut\" setelah pembahasan. Kosong + Buat Tombol Lanjut Otomatis = dibuat dari salinan tombol jawaban A.")]
    public Button lanjutButton;
    [Tooltip("Jika Lanjut Button kosong, buat tombol \"Lanjut\" otomatis (salinan gaya tombol jawaban A).")]
    [SerializeField] private bool buatTombolLanjutOtomatis = true;
    [Tooltip("Dipakai hanya jika tidak ada tombol Lanjut: pembahasan ditutup otomatis setelah sekian detik.")]
    public float pembahasanDuration = 5f;

    [Header("Layar pembahasan (dipakai jika Pembahasan Text kosong)")]
    [Tooltip("Ukuran kotak soal saat menampilkan pembahasan (satuan Canvas, referensi 1920x1080).")]
    [SerializeField] private Vector2 ukuranKotakPembahasan = new Vector2(1450f, 640f);
    [SerializeField] private float posisiYKotakPembahasan = 70f;
    [SerializeField] private float ukuranHurufPembahasanMaks = 44f;
    [SerializeField] private float ukuranHurufPembahasanMin = 22f;

    [Header("Settings")]
    public float quizDuration = 30f;
    public float feedbackDuration = 1f;
    [Tooltip("Lama pesan hasil trap ditampilkan (detik).")]
    public float hasilTrapDuration = 2.5f;
    public bool pauseGameDuringQuiz = true;

    [Header("Nyawa Player")]
    [SerializeField] private PlayerHealth playerHealth;
    [SerializeField] private GameObject healthPanel;

    private static readonly string[] HurufJawaban = { "A", "B", "C", "D" };

    private int idSiswa;
    private bool sesiBerjalan;
    private bool menungguJawaban;
    private string jawabanDipilih;
    private bool lanjutDitekan;

    // ---- Status read-only (dibaca tes otomatis di Editor: Assets/Editor/TesQuizOtomatis.cs) ----
    public bool SesiBerjalan => sesiBerjalan;
    public bool MenungguJawaban => menungguJawaban;
    public int IdSiswa => idSiswa;
    public int LevelGame => levelGame;
    public TrapInfo TrapAktif { get; private set; }
    public SoalData SoalAktif { get; private set; }
    public SubmitJawabanResponse HasilTerakhir { get; private set; }
    /// <summary>Bertambah setiap ada soal baru yang ditampilkan.</summary>
    public int JumlahSoalDitampilkan { get; private set; }
    /// <summary>Bertambah setiap jawaban berhasil disimpan server.</summary>
    public int JumlahJawabanTersimpan { get; private set; }

#if UNITY_EDITOR
    /// <summary>Hanya di Editor: dipakai tes otomatis jika belum login.</summary>
    public void SetIdSiswaUntukTes(int id)
    {
        if (id > 0) idSiswa = id;
    }
#endif

    private void Awake()
    {
        Instance = this;

        SiapkanTombolLanjut();

        if (lanjutButton != null)
        {
            lanjutButton.onClick.AddListener(() => lanjutDitekan = true);
        }
    }

    private void OnDestroy()
    {
        if (Instance == this)
        {
            Instance = null;
        }
    }

    private void Start()
    {
        idSiswa = PlayerPrefs.GetInt("id_siswa", 0);

#if UNITY_EDITOR
        if (idSiswa <= 0 && idSiswaTesEditor > 0)
        {
            idSiswa = idSiswaTesEditor;
            Debug.LogWarning("Belum login: memakai idSiswaTesEditor = " + idSiswa + " (hanya di Editor).");
        }
#endif

        if (levelGame <= 0)
        {
            levelGame = AmbilLevelDariNamaScene();
        }

        if (levelGame <= 0)
        {
            Debug.LogError("Level game tidak diketahui. Isi 'Level Game' di QuizManager atau beri nama scene \"Level 01\" dst.");
        }

        CekTombolJawaban();

        if (quizPanel != null) quizPanel.SetActive(false);
        if (feedbackImage != null) feedbackImage.gameObject.SetActive(false);
        if (pembahasanText != null) pembahasanText.gameObject.SetActive(false);
        if (lanjutButton != null) lanjutButton.gameObject.SetActive(false);

        // Saat game mulai, panel nyawa harus muncul.
        if (healthPanel != null) healthPanel.SetActive(true);

        UpdateTimerText(quizDuration);
    }

    // ------------------------------------------------------------------
    // API untuk QuizTrigger & tombol
    // ------------------------------------------------------------------

    /// <summary>
    /// Membuka sesi trap. false jika sesi lain masih berjalan.
    /// </summary>
    public bool OpenQuiz(QuizTrigger trap)
    {
        if (sesiBerjalan || trap == null)
        {
            return false;
        }

        sesiBerjalan = true;
        StartCoroutine(JalankanSesiTrap(trap));
        return true;
    }

    public void ClickAnswer0() { PilihJawaban(0); }
    public void ClickAnswer1() { PilihJawaban(1); }
    public void ClickAnswer2() { PilihJawaban(2); }
    public void ClickAnswer3() { PilihJawaban(3); }

    private void PilihJawaban(int indeks)
    {
        if (!menungguJawaban)
        {
            return;
        }

        jawabanDipilih = HurufJawaban[indeks];
        menungguJawaban = false;
    }

    // ------------------------------------------------------------------
    // Sesi trap
    // ------------------------------------------------------------------

    private IEnumerator JalankanSesiTrap(QuizTrigger trap)
    {
        BukaPanel();
        TrapAktif = null;

        if (idSiswa <= 0)
        {
            yield return TampilkanPesan("Kamu belum login.\nSilakan login dulu agar jawabanmu tercatat.", hasilTrapDuration);
            BatalkanSesi(trap);
            yield break;
        }

        if (levelGame <= 0)
        {
            yield return TampilkanPesan("Pengaturan level belum benar.\nHubungi guru.", hasilTrapDuration);
            BatalkanSesi(trap);
            yield break;
        }

        int nomorTrap = trap.nomorTrap;
        int minSoal = 3;
        int maxSoal = 5;
        float batasMastery = 0.95f;
        float nilaiMenguasai = 80f;
        float nilaiCukup = 60f;
        string kategori = KategoriBelum;
        int idSkillSesi = 0;
        string namaSkill = "";
        int dijawab = 0;
        int benarSesi = 0;
        // Soal yang sudah muncul di sesi ini; dikirim ke server agar tidak diulang di sesi yang sama.
        var soalSesi = new List<int>();
        // Benar/salah tiap soal di sesi ini (untuk aturan Menguasai).
        var jawabanSesi = new List<bool>();
        bool mastery = false;
        float batasCukup = 0.5f;
        float pKnowAkhir = 0f;

        while (true)
        {
            // 1. Ambil soal berikutnya untuk trap ini.
            SoalResponse soalResp = null;
            string pesanTolak = null;
            yield return AmbilSoal(nomorTrap, idSkillSesi, soalSesi, r => soalResp = r, p => pesanTolak = p);

            if (soalResp == null)
            {
                yield return TampilkanPesan(pesanTolak, hasilTrapDuration);
                BatalkanSesi(trap);
                yield break;
            }

            if (dijawab == 0 && soalResp.trap != null && soalResp.trap.id_skill > 0)
            {
                // Skill dikunci untuk seluruh sesi (penting untuk trap remedial).
                TrapAktif = soalResp.trap;
                idSkillSesi = soalResp.trap.id_skill;
                namaSkill = soalResp.trap.nama_skill;
                if (soalResp.trap.min_soal > 0) minSoal = soalResp.trap.min_soal;
                if (soalResp.trap.max_soal > 0) maxSoal = soalResp.trap.max_soal;
                if (soalResp.trap.batas_mastery > 0f) batasMastery = soalResp.trap.batas_mastery;
                if (soalResp.trap.nilai_menguasai > 0f) nilaiMenguasai = soalResp.trap.nilai_menguasai;
                if (soalResp.trap.nilai_cukup > 0f) nilaiCukup = soalResp.trap.nilai_cukup;
                if (soalResp.trap.batas_cukup > 0f) batasCukup = soalResp.trap.batas_cukup;

                Debug.Log("Trap " + nomorTrap + " | Skill: " + namaSkill + " | Tingkat: " + soalResp.trap.tingkat +
                          " | Remedial: " + soalResp.trap.remedial + " | P(L) awal: " + soalResp.trap.p_know);
            }

            SoalData soal = soalResp.data[0];
            soalSesi.Add(soal.id_soal);

            // 2. Tampilkan soal dan tunggu jawaban (atau waktu habis).
            SoalAktif = soal;
            JumlahSoalDitampilkan++;
            TampilkanSoal(soal);
            string jawaban = null;
            yield return TungguJawaban(j => jawaban = j);

            // 3. Kirim jawaban; server menghitung P(L) baru.
            SubmitJawabanResponse hasil = null;
            pesanTolak = null;
            yield return KirimJawaban(soal.id_soal, jawaban, r => hasil = r, p => pesanTolak = p);

            if (hasil == null)
            {
                yield return TampilkanPesan(pesanTolak, hasilTrapDuration);
                BatalkanSesi(trap);
                yield break;
            }

            dijawab++;
            if (hasil.benar) benarSesi++;
            HasilTerakhir = hasil;
            JumlahJawabanTersimpan++;
            jawabanSesi.Add(hasil.benar);
            pKnowAkhir = hasil.p_know;
            kategori = KategoriTrap(pKnowAkhir, jawabanSesi, batasMastery, batasCukup, nilaiMenguasai, nilaiCukup);
            mastery = kategori == KategoriMenguasai;

            Debug.Log("Soal " + dijawab + "/" + maxSoal + " | id_soal " + soal.id_soal + " | Jawab: " + jawaban +
                      " | Benar: " + hasil.benar + " | P(L): " + hasil.p_know + " | Mastery: " + mastery);

            if (ScoreManager.Instance != null)
            {
                if (hasil.benar) ScoreManager.Instance.JawabanBenar();
                else ScoreManager.Instance.JawabanSalah();
            }
            else
            {
                Debug.LogError("ScoreManager belum ditemukan.");
            }

            // 4. Umpan balik (+ pembahasan jika salah).
            yield return TampilkanUmpanBalik(soal, hasil);

            // 5. Aturan berhenti.
            if ((dijawab >= minSoal && mastery) || dijawab >= maxSoal)
            {
                break;
            }
        }

        // Hasil trap menurut P(L) akhir (kategori sama dengan dashboard guru).
        Debug.Log("Trap " + nomorTrap + " selesai | P(L) akhir: " + pKnowAkhir + " | Mastery: " + mastery);

        // Catat hasil trap untuk dashboard guru (dikirim di latar belakang).
        PengirimData.KirimHasilTrap(new HasilTrapRequest
        {
            id_siswa = idSiswa,
            level_game = levelGame,
            nomor_trap = nomorTrap,
            id_skill = idSkillSesi,
            jumlah_soal = dijawab,
            jumlah_benar = benarSesi,
            p_awal = TrapAktif != null ? TrapAktif.p_know : 0f
        });

        if (mastery)
        {
            yield return TampilkanPesan("Hebat! Kamu sudah menguasai\n\"" + namaSkill + "\".", hasilTrapDuration);
        }
        else if (kategori == KategoriCukup)
        {
            // Cukup menguasai: nyawa tetap. Skill ini akan dilatih lagi di trap berikutnya/remedial.
            yield return TampilkanPesan("Bagus! Kamu hampir menguasai\n\"" + namaSkill + "\".\nAyo latihan lagi di trap berikutnya!", hasilTrapDuration);
        }
        else
        {
            yield return TampilkanPesan("Kamu belum menguasai\n\"" + namaSkill + "\".\nNyawa berkurang 1. Ayo terus berlatih!", hasilTrapDuration);

            if (playerHealth != null)
            {
                playerHealth.LoseLife();
            }
            else
            {
                Debug.LogWarning("PlayerHealth belum dihubungkan ke QuizManager.");
            }

            if (playerHealth != null && playerHealth.IsDead)
            {
                // Game tetap dijeda; PlayerHealth memuat ulang level.
                SetStatus("Nyawa habis! Level diulang.");
                yield break;
            }
        }

        trap.gameObject.SetActive(false);
        TutupPanel();
        sesiBerjalan = false;
    }

    public const string KategoriMenguasai = "Menguasai";
    public const string KategoriCukup = "Cukup Menguasai";
    public const string KategoriBelum = "Belum Menguasai";

    /// <summary>
    /// Kategori satu trap dari P(L) dan nilai (% benar di trap ini).
    /// Sama dengan kategoriPenguasaan() di config/bkt.php. Syarat nilai mencegah siswa yang
    /// P(L)-nya sudah tinggi dari trap sebelumnya dinilai menguasai walau salah 2 dari 3 soal.
    /// </summary>
    public static string KategoriTrap(float pKnow, List<bool> jawaban, float batasMastery, float batasCukup,
                                      float nilaiMenguasai, float nilaiCukup)
    {
        if (jawaban == null || jawaban.Count == 0)
        {
            return KategoriBelum;
        }

        int benar = 0;
        foreach (bool b in jawaban)
        {
            if (b) benar++;
        }

        // Dibulatkan 2 desimal seperti nilaiBenar() di server (2/3 = 66.67).
        float nilai = Mathf.Round(benar * 10000f / jawaban.Count) / 100f;

        if (pKnow >= batasMastery && nilai >= nilaiMenguasai) return KategoriMenguasai;
        if (pKnow >= batasCukup && nilai >= nilaiCukup) return KategoriCukup;
        return KategoriBelum;
    }

    /// <summary>Sesi batal tanpa penilaian dan tanpa mengurangi nyawa; trap bisa dibuka lagi.</summary>
    private void BatalkanSesi(QuizTrigger trap)
    {
        TutupPanel();
        sesiBerjalan = false;

        if (trap != null)
        {
            trap.IzinkanBukaLagi();
        }
    }

    // ------------------------------------------------------------------
    // Jaringan
    // ------------------------------------------------------------------

    /// <summary>
    /// Mengambil satu soal. Gagal koneksi dicoba ulang terus (soal tidak boleh terlewat).
    /// Jika server menjawab tapi menolak (success=false), panggil tolak(pesan).
    /// </summary>
    private IEnumerator AmbilSoal(int nomorTrap, int idSkill, List<int> soalSesi,
                                  Action<SoalResponse> ok, Action<string> tolak)
    {
        string url = getSoalUrl + (getSoalUrl.Contains("?") ? "&" : "?") +
                     "mode=trap&id_siswa=" + idSiswa +
                     "&level=" + levelGame +
                     "&trap=" + nomorTrap +
                     (idSkill > 0 ? "&id_skill=" + idSkill : "") +
                     (soalSesi != null && soalSesi.Count > 0 ? "&kecuali=" + string.Join(",", soalSesi) : "");

        int percobaan = 0;

        while (true)
        {
            percobaan++;

            using (UnityWebRequest request = UnityWebRequest.Get(url))
            {
                request.timeout = requestTimeout;
                yield return request.SendWebRequest();

                if (request.result == UnityWebRequest.Result.Success)
                {
                    string json = request.downloadHandler.text;
                    SoalResponse response = BacaJson<SoalResponse>(json);

                    if (response != null && response.success && response.data != null && response.data.Length > 0)
                    {
                        ok(response);
                        yield break;
                    }

                    Debug.LogWarning("get_soal ditolak: " + json);
                    tolak(response != null && !string.IsNullOrEmpty(response.message)
                        ? "Soal tidak bisa dimuat:\n" + response.message
                        : "Respons server tidak valid.");
                    yield break;
                }

                Debug.LogWarning("Gagal mengambil soal (percobaan " + percobaan + "): " + request.error + " | " + url);
            }

            SetStatus("Tidak bisa terhubung ke server.\nMencoba lagi... (" + percobaan + ")");
            yield return new WaitForSecondsRealtime(jedaCobaUlang);
        }
    }

    /// <summary>
    /// Mengirim jawaban. Gagal koneksi dicoba ulang terus dengan kode_kirim yang sama,
    /// sehingga jawaban tidak hilang dan tidak tercatat dua kali.
    /// </summary>
    private IEnumerator KirimJawaban(int idSoal, string jawaban, Action<SubmitJawabanResponse> ok, Action<string> tolak)
    {
        SubmitJawabanRequest data = new SubmitJawabanRequest
        {
            id_siswa = idSiswa,
            id_soal = idSoal,
            jawaban_siswa = jawaban,
            kode_kirim = Guid.NewGuid().ToString("N")
        };

        byte[] body = Encoding.UTF8.GetBytes(JsonUtility.ToJson(data));
        int percobaan = 0;

        while (true)
        {
            percobaan++;

            using (UnityWebRequest request = new UnityWebRequest(submitJawabanUrl, "POST"))
            {
                request.uploadHandler = new UploadHandlerRaw(body);
                request.downloadHandler = new DownloadHandlerBuffer();
                request.SetRequestHeader("Content-Type", "application/json");
                request.timeout = requestTimeout;

                yield return request.SendWebRequest();

                if (request.result == UnityWebRequest.Result.Success)
                {
                    string json = request.downloadHandler.text;
                    SubmitJawabanResponse response = BacaJson<SubmitJawabanResponse>(json);

                    if (response != null && response.success)
                    {
                        ok(response);
                        yield break;
                    }

                    Debug.LogWarning("submit_jawaban ditolak: " + json);
                    tolak(response != null && !string.IsNullOrEmpty(response.message)
                        ? "Jawaban tidak bisa disimpan:\n" + response.message
                        : "Respons server tidak valid.");
                    yield break;
                }

                Debug.LogWarning("Gagal mengirim jawaban (percobaan " + percobaan + "): " + request.error);
            }

            SetStatus("Jawabanmu belum terkirim.\nMencoba lagi... (" + percobaan + ")");
            yield return new WaitForSecondsRealtime(jedaCobaUlang);
        }
    }

    private static T BacaJson<T>(string json) where T : class
    {
        if (string.IsNullOrWhiteSpace(json))
        {
            return null;
        }

        try
        {
            return JsonUtility.FromJson<T>(json);
        }
        catch (Exception e)
        {
            Debug.LogError("JSON tidak valid: " + e.Message + "\n" + json);
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Tampilan
    // ------------------------------------------------------------------

    private void BukaPanel()
    {
        // Sembunyikan nyawa & kontrol selama quiz.
        if (healthPanel != null) healthPanel.SetActive(false);
        if (controlPanel != null) controlPanel.SetActive(false);

        // Reset flag tombol gerak agar tidak "nyangkut" saat panel dimatikan.
        if (mobileController != null) mobileController.ResetGerakan();

        if (quizPanel != null) quizPanel.SetActive(true);
        if (feedbackImage != null) feedbackImage.gameObject.SetActive(false);
        if (pembahasanText != null) pembahasanText.gameObject.SetActive(false);
        if (lanjutButton != null) lanjutButton.gameObject.SetActive(false);

        if (pauseGameDuringQuiz) Time.timeScale = 0f;

        UpdateTimerText(quizDuration);
        SetStatus("Memuat soal...");
    }

    private void TutupPanel()
    {
        menungguJawaban = false;
        KeluarLayarPembahasan();

        if (feedbackImage != null) feedbackImage.gameObject.SetActive(false);
        if (pembahasanText != null) pembahasanText.gameObject.SetActive(false);
        if (lanjutButton != null) lanjutButton.gameObject.SetActive(false);
        if (quizPanel != null) quizPanel.SetActive(false);

        // Munculkan lagi nyawa & kontrol saat kembali ke adventure.
        if (healthPanel != null) healthPanel.SetActive(true);
        if (controlPanel != null) controlPanel.SetActive(true);

        if (pauseGameDuringQuiz) Time.timeScale = 1f;
    }

    /// <summary>Menampilkan pesan di area pertanyaan; tombol jawaban dikosongkan.</summary>
    private void SetStatus(string pesan)
    {
        if (questionText != null)
        {
            questionText.text = pesan;
        }

        for (int i = 0; i < 4; i++)
        {
            SetAnswerButton(i, "");
        }

        SetButtonsInteractable(false);
    }

    private IEnumerator TampilkanPesan(string pesan, float durasi)
    {
        SetStatus(pesan);
        yield return new WaitForSecondsRealtime(durasi);
    }

    private void TampilkanSoal(SoalData soal)
    {
        if (feedbackImage != null) feedbackImage.gameObject.SetActive(false);
        if (pembahasanText != null) pembahasanText.gameObject.SetActive(false);

        if (questionText != null)
        {
            questionText.text = soal.pertanyaan;
        }

        SetAnswerButton(0, soal.pilihan_a);
        SetAnswerButton(1, soal.pilihan_b);
        SetAnswerButton(2, soal.pilihan_c);
        SetAnswerButton(3, soal.pilihan_d);
    }

    /// <summary>Mengaktifkan tombol dan timer; hasilnya huruf A-D atau "TIMEOUT".</summary>
    private IEnumerator TungguJawaban(Action<string> hasil)
    {
        jawabanDipilih = null;
        menungguJawaban = true;
        SetButtonsInteractable(true);

        float sisaWaktu = quizDuration;
        UpdateTimerText(sisaWaktu);

        while (menungguJawaban && sisaWaktu > 0f)
        {
            sisaWaktu = Mathf.Max(0f, sisaWaktu - Time.unscaledDeltaTime);
            UpdateTimerText(sisaWaktu);
            yield return null;
        }

        menungguJawaban = false;
        SetButtonsInteractable(false);

        if (jawabanDipilih == null)
        {
            Debug.Log("Waktu quiz habis.");
        }

        hasil(jawabanDipilih ?? "TIMEOUT");
    }

    private IEnumerator TampilkanUmpanBalik(SoalData soal, SubmitJawabanResponse hasil)
    {
        if (feedbackImage != null)
        {
            feedbackImage.sprite = hasil.benar ? correctSprite : wrongSprite;
            feedbackImage.gameObject.SetActive(true);
        }

        yield return new WaitForSecondsRealtime(feedbackDuration);

        if (feedbackImage != null)
        {
            feedbackImage.gameObject.SetActive(false);
        }

        if (hasil.benar)
        {
            yield break;
        }

        // Salah / waktu habis: tunjukkan jawaban benar dan pembahasannya.
        // Ini "kesempatan belajar" yang diasumsikan parameter P(T) pada BKT.
        StringBuilder teks = new StringBuilder();
        teks.Append(hasil.jawaban_siswa == "-" ? "Waktu habis.\n" : "Jawabanmu belum tepat.\n");
        teks.Append("Jawaban benar: ").Append(hasil.jawaban_benar).Append(". ").Append(TeksPilihan(soal, hasil.jawaban_benar));

        if (!string.IsNullOrWhiteSpace(hasil.pembahasan))
        {
            teks.Append("\n\n").Append(hasil.pembahasan);
        }

        // Layar khusus pembahasan: soal, pilihan jawaban, dan timer disembunyikan;
        // yang tersisa hanya latar, kotak pembahasan, dan tombol Lanjut.
        MasukLayarPembahasan(teks.ToString());

        if (lanjutButton != null)
        {
            lanjutDitekan = false;
            lanjutButton.interactable = true;
            lanjutButton.gameObject.SetActive(true);
            yield return new WaitUntil(() => lanjutDitekan);
            lanjutButton.gameObject.SetActive(false);
        }
        else
        {
            yield return new WaitForSecondsRealtime(pembahasanDuration);
        }

        KeluarLayarPembahasan();
    }

    // ------------------------------------------------------------------
    // Layar pembahasan
    // ------------------------------------------------------------------

    private readonly List<GameObject> disembunyikanSaatPembahasan = new List<GameObject>();
    private bool layarPembahasanAktif;

    // Tata letak asli kotak soal & teks soal (dikembalikan setelah pembahasan).
    private RectTransform kotakTeksPembahasan;
    private Vector2 asliKotakAnchorMin, asliKotakAnchorMax, asliKotakPos, asliKotakSize;
    private Vector2 asliTeksAnchorMin, asliTeksAnchorMax, asliTeksPos, asliTeksSize;
    private bool asliAutoSize;
    private float asliFontSize, asliFontMin, asliFontMax;

    /// <summary>Anak langsung quizPanel yang memuat objek t (misal QuestionPanel untuk QuestionText).</summary>
    private Transform AnakQuizPanel(Transform t)
    {
        if (quizPanel == null || t == null) return null;

        Transform panel = quizPanel.transform;
        while (t != null && t.parent != panel)
        {
            t = t.parent;
        }

        return t;
    }

    private void MasukLayarPembahasan(string isi)
    {
        TMP_Text teks = pembahasanText != null ? pembahasanText : questionText;
        if (teks == null) return;

        if (!layarPembahasanAktif && quizPanel != null)
        {
            layarPembahasanAktif = true;
            disembunyikanSaatPembahasan.Clear();

            Transform kotak = AnakQuizPanel(teks.transform);
            Transform kotakLanjut = lanjutButton != null ? AnakQuizPanel(lanjutButton.transform) : null;

            // Sembunyikan semua isi panel quiz kecuali kotak pembahasan & tombol Lanjut.
            // Latar (Image milik quizPanel sendiri) tetap terlihat.
            foreach (Transform anak in quizPanel.transform)
            {
                if (anak == kotak || anak == kotakLanjut || !anak.gameObject.activeSelf) continue;

                anak.gameObject.SetActive(false);
                disembunyikanSaatPembahasan.Add(anak.gameObject);
            }

            // Jika memakai teks soal (bukan Pembahasan Text khusus), besarkan kotaknya
            // ke tengah layar agar pembahasan panjang tidak keluar kotak.
            if (pembahasanText == null && kotak != null && kotak != teks.transform)
            {
                kotakTeksPembahasan = kotak as RectTransform;
                RectTransform rtTeks = teks.rectTransform;

                if (kotakTeksPembahasan != null)
                {
                    asliKotakAnchorMin = kotakTeksPembahasan.anchorMin;
                    asliKotakAnchorMax = kotakTeksPembahasan.anchorMax;
                    asliKotakPos = kotakTeksPembahasan.anchoredPosition;
                    asliKotakSize = kotakTeksPembahasan.sizeDelta;

                    asliTeksAnchorMin = rtTeks.anchorMin;
                    asliTeksAnchorMax = rtTeks.anchorMax;
                    asliTeksPos = rtTeks.anchoredPosition;
                    asliTeksSize = rtTeks.sizeDelta;

                    asliAutoSize = teks.enableAutoSizing;
                    asliFontSize = teks.fontSize;
                    asliFontMin = teks.fontSizeMin;
                    asliFontMax = teks.fontSizeMax;

                    kotakTeksPembahasan.anchorMin = new Vector2(0.5f, 0.5f);
                    kotakTeksPembahasan.anchorMax = new Vector2(0.5f, 0.5f);
                    kotakTeksPembahasan.anchoredPosition = new Vector2(0f, posisiYKotakPembahasan);
                    kotakTeksPembahasan.sizeDelta = ukuranKotakPembahasan;

                    // Teks mengisi kotak dengan jarak tepi 50.
                    rtTeks.anchorMin = Vector2.zero;
                    rtTeks.anchorMax = Vector2.one;
                    rtTeks.anchoredPosition = Vector2.zero;
                    rtTeks.sizeDelta = new Vector2(-100f, -100f);

                    teks.enableAutoSizing = true;
                    teks.fontSizeMin = ukuranHurufPembahasanMin;
                    teks.fontSizeMax = ukuranHurufPembahasanMaks;
                }
            }
        }

        teks.text = isi;
        teks.gameObject.SetActive(true);
    }

    private void KeluarLayarPembahasan()
    {
        if (!layarPembahasanAktif) return;
        layarPembahasanAktif = false;

        if (kotakTeksPembahasan != null && questionText != null)
        {
            kotakTeksPembahasan.anchorMin = asliKotakAnchorMin;
            kotakTeksPembahasan.anchorMax = asliKotakAnchorMax;
            kotakTeksPembahasan.anchoredPosition = asliKotakPos;
            kotakTeksPembahasan.sizeDelta = asliKotakSize;

            RectTransform rtTeks = questionText.rectTransform;
            rtTeks.anchorMin = asliTeksAnchorMin;
            rtTeks.anchorMax = asliTeksAnchorMax;
            rtTeks.anchoredPosition = asliTeksPos;
            rtTeks.sizeDelta = asliTeksSize;

            questionText.enableAutoSizing = asliAutoSize;
            questionText.fontSizeMin = asliFontMin;
            questionText.fontSizeMax = asliFontMax;
            questionText.fontSize = asliFontSize;

            // Jangan tampilkan sisa pembahasan di kotak kecil selagi soal berikutnya dimuat.
            questionText.text = "";
        }

        kotakTeksPembahasan = null;

        if (pembahasanText != null) pembahasanText.gameObject.SetActive(false);
        if (lanjutButton != null) lanjutButton.gameObject.SetActive(false);

        foreach (GameObject go in disembunyikanSaatPembahasan)
        {
            if (go != null) go.SetActive(true);
        }

        disembunyikanSaatPembahasan.Clear();

        // Gambar benar/salah tidak ikut muncul lagi.
        if (feedbackImage != null) feedbackImage.gameObject.SetActive(false);
    }

    /// <summary>
    /// Jika belum ada tombol Lanjut di scene, buat dari salinan tombol jawaban A
    /// (gaya sama), diletakkan di tengah bawah, tidak aktif sampai pembahasan muncul.
    /// </summary>
    private void SiapkanTombolLanjut()
    {
        if (lanjutButton != null || !buatTombolLanjutOtomatis || quizPanel == null ||
            answerButtons == null || answerButtons.Length == 0 || answerButtons[0] == null)
        {
            return;
        }

        GameObject salinan = Instantiate(answerButtons[0].gameObject, quizPanel.transform, false);
        salinan.name = "LanjutButton (otomatis)";

        Button tombol = salinan.GetComponent<Button>();
        // Buang event ClickAnswer0 bawaan salinan.
        tombol.onClick = new Button.ButtonClickedEvent();
        tombol.interactable = true;

        RectTransform rt = (RectTransform)salinan.transform;
        rt.anchorMin = new Vector2(0.5f, 0f);
        rt.anchorMax = new Vector2(0.5f, 0f);
        rt.pivot = new Vector2(0.5f, 0.5f);
        rt.anchoredPosition = new Vector2(0f, 120f);
        rt.sizeDelta = new Vector2(480f, 140f);

        TMP_Text label = salinan.GetComponentInChildren<TMP_Text>(true);
        if (label != null)
        {
            label.text = "Lanjut";
            label.rectTransform.anchorMin = Vector2.zero;
            label.rectTransform.anchorMax = Vector2.one;
            label.rectTransform.anchoredPosition = Vector2.zero;
            label.rectTransform.sizeDelta = Vector2.zero;
        }

        salinan.SetActive(false);
        lanjutButton = tombol;
    }

    private static string TeksPilihan(SoalData soal, string huruf)
    {
        switch (huruf)
        {
            case "A": return soal.pilihan_a;
            case "B": return soal.pilihan_b;
            case "C": return soal.pilihan_c;
            case "D": return soal.pilihan_d;
            default: return "";
        }
    }

    private void SetAnswerButton(int index, string answerText)
    {
        if (answerButtons == null || index >= answerButtons.Length || answerButtons[index] == null)
        {
            return;
        }

        TMP_Text buttonText = answerButtons[index].GetComponentInChildren<TMP_Text>();

        if (buttonText != null)
        {
            buttonText.text = answerText;
        }
    }

    private void SetButtonsInteractable(bool value)
    {
        if (answerButtons == null)
        {
            return;
        }

        foreach (Button button in answerButtons)
        {
            if (button != null)
            {
                button.interactable = value;
            }
        }
    }

    private void UpdateTimerText(float timeValue)
    {
        if (timerText == null)
        {
            return;
        }

        int minutes = Mathf.FloorToInt(timeValue / 60f);
        int seconds = Mathf.CeilToInt(timeValue % 60f);

        if (seconds == 60)
        {
            minutes++;
            seconds = 0;
        }

        timerText.text = minutes.ToString("00") + ":" + seconds.ToString("00");
    }

    // ------------------------------------------------------------------
    // Pengecekan konfigurasi
    // ------------------------------------------------------------------

    private static int AmbilLevelDariNamaScene()
    {
        Match angka = Regex.Match(SceneManager.GetActiveScene().name, @"\d+");
        return angka.Success ? int.Parse(angka.Value) : 0;
    }

    /// <summary>
    /// Memastikan tombol jawaban ke-i memanggil ClickAnswer{i}.
    /// (Pernah terjadi: tombol D memanggil ClickAnswer2 sehingga jawaban D terkirim sebagai C.)
    /// </summary>
    private void CekTombolJawaban()
    {
        if (answerButtons == null || answerButtons.Length != 4)
        {
            Debug.LogError("QuizManager: Answer Buttons harus berisi 4 tombol (A, B, C, D).");
            return;
        }

        for (int i = 0; i < answerButtons.Length; i++)
        {
            Button tombol = answerButtons[i];

            if (tombol == null)
            {
                Debug.LogError("QuizManager: tombol jawaban " + HurufJawaban[i] + " kosong.");
                continue;
            }

            string seharusnya = "ClickAnswer" + i;
            bool cocok = false;

            for (int j = 0; j < tombol.onClick.GetPersistentEventCount(); j++)
            {
                string metode = tombol.onClick.GetPersistentMethodName(j);

                if (metode == seharusnya)
                {
                    cocok = true;
                }
                else if (metode.StartsWith("ClickAnswer"))
                {
                    Debug.LogError("Tombol " + HurufJawaban[i] + " (" + tombol.name + ") memanggil " + metode +
                                   ", seharusnya " + seharusnya + ".");
                }
            }

            if (!cocok)
            {
                Debug.LogError("Tombol " + HurufJawaban[i] + " (" + tombol.name + ") tidak memanggil " + seharusnya + ".");
            }
        }
    }
}
