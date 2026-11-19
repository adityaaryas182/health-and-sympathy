using UnityEngine;
using UnityEngine.Serialization;

/// <summary>
/// Trap quiz. Saat Player menyentuh trap, QuizManager menjalankan satu sesi trap
/// (beberapa soal dari satu skill, berhenti saat mastery BKT atau batas soal).
///
/// Nomor trap menentukan isi trap (diatur backend, lihat config/bkt.php):
///   1 = skill pertama level ini, tingkat dasar
///   2 = skill pertama, tingkat lanjut
///   3 = skill kedua, tingkat dasar
///   4 = skill kedua, tingkat lanjut
///   5 = remedial (skill dengan P(L) terendah di level ini)
/// </summary>
public class QuizTrigger : MonoBehaviour
{
    // FormerlySerializedAs: nilai lama "quizId" (1-5) di scene tetap terbaca.
    [FormerlySerializedAs("quizId")]
    [Range(1, 5)]
    [Tooltip("1-2 = skill pertama (dasar, lanjut), 3-4 = skill kedua (dasar, lanjut), 5 = remedial.")]
    public int nomorTrap = 1;

    private bool sudahTrigger = false;

    private void OnTriggerEnter2D(Collider2D other)
    {
        if (sudahTrigger || !other.CompareTag("Player"))
        {
            return;
        }

        if (QuizManager.Instance == null)
        {
            Debug.LogError("QuizManager tidak ada di scene. Trap " + nomorTrap + " tidak bisa dibuka.");
            return;
        }

        // OpenQuiz menolak jika sesi trap lain masih berjalan.
        if (QuizManager.Instance.OpenQuiz(this))
        {
            sudahTrigger = true;
        }
    }

    /// <summary>
    /// Dipanggil QuizManager jika sesi batal tanpa penilaian (misal siswa belum login),
    /// supaya trap bisa dibuka lagi saat Player menyentuhnya kembali.
    /// </summary>
    public void IzinkanBukaLagi()
    {
        sudahTrigger = false;
    }
}
