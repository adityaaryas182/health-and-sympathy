using System.Collections;
using UnityEngine;
using UnityEngine.SceneManagement;

public class PlayerHealth : MonoBehaviour
{
    [Header("Daftar icon hati di Canvas")]
    [SerializeField] private GameObject[] hearts;

    [Header("Pengaturan Buah")]
    [SerializeField] private int fruitsPerLife = 2;

    [Header("Game Over")]
    [Tooltip("Jeda (detik, realtime) sebelum level dimuat ulang, agar feedback quiz sempat terlihat.")]
    [SerializeField] private float gameOverDelay = 1f;

    public int CurrentLives { get; private set; }
    public int CurrentFruit { get; private set; }
    public bool IsDead { get; private set; }

    public int MaxLives
    {
        get
        {
            return hearts.Length;
        }
    }

    private void Start()
    {
        // Nyawa awal mengikuti jumlah icon hati.
        // Karena Heart1, Heart2, Heart3 = 3 nyawa.
        CurrentLives = MaxLives;

        // Saat game mulai, belum ada buah yang dikumpulkan.
        CurrentFruit = 0;

        UpdateHeartUI();
    }

    // Dipanggil QuizManager saat jawaban salah atau timer habis.
    public void LoseLife()
    {
        if (IsDead || CurrentLives <= 0)
            return;

        CurrentLives--;

        UpdateHeartUI();

        Debug.Log("Nyawa tersisa: " + CurrentLives);

        if (CurrentLives <= 0)
        {
            GameOver();
        }
    }

    // Dipanggil oleh buah saat Player menyentuh buah.
    // Buah SELALU bisa diambil (true), kecuali player sudah mati.
    // - Nyawa belum penuh: buah terkumpul; tiap fruitsPerLife buah = +1 hati.
    // - Nyawa penuh: buah tetap diambil dan disimpan sebagai cicilan hati berikutnya,
    //   paling banyak fruitsPerLife - 1 (hati tidak bisa melebihi jumlah maksimal).
    public bool CollectFruit(int fruitAmount = 1)
    {
        if (IsDead)
            return false;

        CurrentFruit += fruitAmount;

        while (CurrentFruit >= fruitsPerLife && CurrentLives < MaxLives)
        {
            CurrentFruit -= fruitsPerLife;
            GainLife();
        }

        if (CurrentLives >= MaxLives)
        {
            CurrentFruit = Mathf.Min(CurrentFruit, Mathf.Max(0, fruitsPerLife - 1));
        }

        Debug.Log("Buah diambil. Cicilan hati: " + CurrentFruit + " / " + fruitsPerLife +
                  " | Nyawa: " + CurrentLives + " / " + MaxLives);

        return true;
    }

    // Menambah satu hati.
    public void GainLife()
    {
        if (IsDead || CurrentLives >= MaxLives)
        {
            return;
        }

        CurrentLives++;

        UpdateHeartUI();

        Debug.Log("Nyawa bertambah. Nyawa sekarang: " + CurrentLives);
    }

    private void UpdateHeartUI()
    {
        for (int i = 0; i < hearts.Length; i++)
        {
            if (hearts[i] != null)
            {
                // Contoh:
                // CurrentLives = 3 -> semua hati aktif
                // CurrentLives = 2 -> Heart1 dan Heart2 aktif
                // CurrentLives = 1 -> hanya Heart1 aktif
                hearts[i].SetActive(i < CurrentLives);
            }
        }
    }

    private void GameOver()
    {
        if (IsDead)
            return;

        IsDead = true;

        Debug.Log("GAME OVER - Nyawa habis");

        // Catat nilai percobaan ini ke dashboard guru sebelum level dimuat ulang
        // (rekap benar/salah ikut ter-reset saat level dimuat ulang).
        PengirimData.KirimNilaiLevel(PengirimData.StatusGameOver);

        StartCoroutine(RestartLevelRoutine());
    }

    // Player mati: muat ulang level yang sedang dimainkan,
    // sama seperti SceneLoader (timeScale dikembalikan dulu).
    private IEnumerator RestartLevelRoutine()
    {
        yield return new WaitForSecondsRealtime(gameOverDelay);

        Time.timeScale = 1f;
        SceneManager.LoadScene(SceneManager.GetActiveScene().name);
    }
}