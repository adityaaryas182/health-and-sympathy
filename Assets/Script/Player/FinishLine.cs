using UnityEngine;

public class FinishLine : MonoBehaviour
{
    [Header("UI Kemenangan")]
    [SerializeField] private GameObject winPanel;

    [Header("Konfigurasi Server")]
    [Tooltip("Kosong = otomatis memakai folder API yang sama dengan QuizManager.")]
    [SerializeField] private string submitNilaiUrl = "http://localhost/backend/api/submit_nilai.php";

    private bool sudahSelesai = false;

    private void OnTriggerEnter2D(Collider2D other)
    {
        if (!other.CompareTag("Player")) return;
        if (sudahSelesai) return;

        sudahSelesai = true;

        // Level selesai: baru sekarang skor level ini disimpan permanen.
        if (ScoreManager.Instance != null)
        {
            ScoreManager.Instance.SimpanScore();
        }

        if (winPanel != null)
        {
            winPanel.SetActive(true);
        }

        Time.timeScale = 0f;

        // Dikirim di latar belakang; tetap jalan walau pemain langsung pindah scene.
        PengirimData.KirimNilaiLevel(PengirimData.StatusSelesai, submitNilaiUrl);
    }
}
