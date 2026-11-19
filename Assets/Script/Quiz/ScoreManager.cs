using TMPro;
using UnityEngine;

public class ScoreManager : MonoBehaviour
{
    public static ScoreManager Instance;

    [Header("UI")]
    [SerializeField] private TMP_Text scoreText;

    [Header("Score Setting")]
    [SerializeField] private int skorJawabanBenar = 10;
    [SerializeField] private int bonusStreak = 5;

    private int score;
    private int streak;
    
    // Variabel penghitung benar/salah untuk rekap level
    private int jumlahBenar;
    private int jumlahSalah;

    private const string ScoreKey = "PLAYER_SCORE";

    /// <summary>
    /// Mengosongkan skor yang tersimpan di perangkat. Dipanggil saat siswa login,
    /// supaya skor siswa sebelumnya (di HP/PC yang sama) tidak terbawa.
    /// Bisa dipanggil dari scene mana pun, walau ScoreManager tidak ada di scene itu.
    /// </summary>
    public static void HapusSkorTersimpan()
    {
        PlayerPrefs.SetInt(ScoreKey, 0);
        PlayerPrefs.Save();
    }

    private void Awake()
    {
        // Singleton supaya bisa dipanggil dari script mana pun.
        if (Instance != null && Instance != this)
        {
            Destroy(gameObject);
            return;
        }

        Instance = this;
    }

    private void Start()
    {
        LoadScore();
        UpdateScoreUI();
    }

    public void JawabanBenar()
    {
        jumlahBenar++;
        streak++;

        int poinDidapat = skorJawabanBenar;

        // Bonus setiap 3 jawaban benar berturut-turut.
        if (streak % 3 == 0)
        {
            poinDidapat += bonusStreak;
        }

        TambahScore(poinDidapat);
    }

    public void JawabanSalah()
    {
        jumlahSalah++;
        
        // Skor tidak dikurangi, hanya streak di-reset.
        streak = 0;
    }

    public void TambahScore(int jumlah)
    {
        // Sengaja TIDAK langsung disimpan ke PlayerPrefs.
        // Skor baru disimpan lewat SimpanScore() saat level selesai (FinishLine),
        // sehingga saat game over (level dimuat ulang) skor kembali ke nilai awal level
        // dan tetap sejalan dengan jumlah benar/salah yang juga ter-reset.
        score += jumlah;

        UpdateScoreUI();

        Debug.Log($"Score bertambah {jumlah}. Total score: {score}");
    }

    public int GetScore()
    {
        return score;
    }
    
    public int GetJumlahBenar() => jumlahBenar;
    public int GetJumlahSalah() => jumlahSalah;

    // Dipanggil saat level berhasil diselesaikan.
    public void SimpanScore()
    {
        PlayerPrefs.SetInt(ScoreKey, score);
        PlayerPrefs.Save();
    }

    public void ResetScore()
    {
        score = 0;
        streak = 0;
        jumlahBenar = 0;
        jumlahSalah = 0;

        PlayerPrefs.SetInt(ScoreKey, 0);
        PlayerPrefs.Save();

        UpdateScoreUI();
    }

    private void LoadScore()
    {
        score = PlayerPrefs.GetInt(ScoreKey, 0);
    }

    private void UpdateScoreUI()
    {
        if (scoreText != null)
        {
            scoreText.text = $"Score: {score}";
        }
    }
}