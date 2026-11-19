using System;
using System.Collections;
using System.Text;
using System.Text.RegularExpressions;
using UnityEngine;
using UnityEngine.Networking;
using UnityEngine.SceneManagement;

[Serializable]
public class NilaiRequest
{
    public int id_siswa;
    public int level_game;
    public string status;
    public int skor;
    public int poin_game;
    public int jumlah_soal;
    public int jumlah_benar;
    public int jumlah_salah;
    public string kode_kirim;
}

[Serializable]
public class HasilTrapRequest
{
    public int id_siswa;
    public int level_game;
    public int nomor_trap;
    public int id_skill;
    public int jumlah_soal;
    public int jumlah_benar;
    public float p_awal;
    public string kode_kirim;
}

[Serializable]
public class KirimDataResponse
{
    public bool success;
    public string message;
}

/// <summary>
/// Mengirim nilai level dan hasil trap ke server di latar belakang.
/// Objeknya DontDestroyOnLoad, jadi kiriman tetap jalan walau level dimuat ulang
/// (game over) atau scene berganti. Gagal koneksi dicoba ulang beberapa kali
/// dengan kode_kirim yang sama, sehingga data tidak tercatat dua kali.
/// </summary>
public class PengirimData : MonoBehaviour
{
    public const string StatusSelesai = "Selesai";
    public const string StatusGameOver = "Game Over";

    private const int MaksPercobaan = 5;
    private const float JedaCobaUlang = 2f;
    private const int BatasWaktu = 10;

    private static PengirimData instance;

    private static PengirimData Runner
    {
        get
        {
            if (instance == null)
            {
                GameObject go = new GameObject("PengirimData");
                DontDestroyOnLoad(go);
                instance = go.AddComponent<PengirimData>();
            }

            return instance;
        }
    }

    /// <summary>
    /// Kirim nilai level yang sedang dimainkan (rekap benar/salah dari ScoreManager).
    /// url kosong = diturunkan dari URL API QuizManager.
    /// </summary>
    public static void KirimNilaiLevel(string status, string url = null)
    {
        if (string.IsNullOrEmpty(url))
        {
            url = UrlApi("submit_nilai.php");
        }

        int idSiswa = IdSiswaAktif();
        int level = LevelAktif();
        ScoreManager sm = ScoreManager.Instance;
        int benar = sm != null ? sm.GetJumlahBenar() : 0;
        int salah = sm != null ? sm.GetJumlahSalah() : 0;
        int jumlahSoal = benar + salah;

        if (string.IsNullOrEmpty(url) || idSiswa <= 0 || jumlahSoal <= 0)
        {
            Debug.LogWarning("Nilai tidak dikirim. url: " + url + " | id_siswa: " + idSiswa + " | jumlah soal: " + jumlahSoal);
            return;
        }

        NilaiRequest data = new NilaiRequest
        {
            id_siswa = idSiswa,
            level_game = level,
            status = status,
            // Nilai resmi = persentase jawaban benar (0-100).
            skor = Mathf.RoundToInt((float)benar / jumlahSoal * 100f),
            poin_game = sm != null ? sm.GetScore() : 0,
            jumlah_soal = jumlahSoal,
            jumlah_benar = benar,
            jumlah_salah = salah,
            kode_kirim = Guid.NewGuid().ToString("N")
        };

        Runner.StartCoroutine(Runner.KirimJson(url, JsonUtility.ToJson(data), "nilai level " + level + " (" + status + ")"));
    }

    /// <summary>Kirim hasil satu trap. Kategori & P(L) akhir ditentukan server.</summary>
    public static void KirimHasilTrap(HasilTrapRequest data)
    {
        string url = UrlApi("submit_trap.php");

        if (string.IsNullOrEmpty(url) || data == null || data.id_siswa <= 0)
        {
            Debug.LogWarning("Hasil trap tidak dikirim (URL/id_siswa kosong).");
            return;
        }

        data.kode_kirim = Guid.NewGuid().ToString("N");
        Runner.StartCoroutine(Runner.KirimJson(url, JsonUtility.ToJson(data), "hasil trap " + data.nomor_trap));
    }

    /// <summary>URL API lain di folder yang sama dengan submitJawabanUrl milik QuizManager.</summary>
    public static string UrlApi(string namaFile)
    {
        QuizManager qm = QuizManager.Instance;

        if (qm == null || string.IsNullOrEmpty(qm.submitJawabanUrl))
        {
            return null;
        }

        string acuan = qm.submitJawabanUrl;
        int tanya = acuan.IndexOf('?');
        if (tanya >= 0) acuan = acuan.Substring(0, tanya);

        int garis = acuan.LastIndexOf('/');
        return garis >= 0 ? acuan.Substring(0, garis + 1) + namaFile : null;
    }

    private static int IdSiswaAktif()
    {
        if (QuizManager.Instance != null && QuizManager.Instance.IdSiswa > 0)
        {
            return QuizManager.Instance.IdSiswa;
        }

        return PlayerPrefs.GetInt("id_siswa", 0);
    }

    private static int LevelAktif()
    {
        if (QuizManager.Instance != null && QuizManager.Instance.LevelGame > 0)
        {
            return QuizManager.Instance.LevelGame;
        }

        Match angka = Regex.Match(SceneManager.GetActiveScene().name, @"\d+");
        return angka.Success ? int.Parse(angka.Value) : 0;
    }

    private IEnumerator KirimJson(string url, string json, string label)
    {
        byte[] body = Encoding.UTF8.GetBytes(json);

        for (int percobaan = 1; percobaan <= MaksPercobaan; percobaan++)
        {
            using (UnityWebRequest request = new UnityWebRequest(url, "POST"))
            {
                request.uploadHandler = new UploadHandlerRaw(body);
                request.downloadHandler = new DownloadHandlerBuffer();
                request.SetRequestHeader("Content-Type", "application/json");
                request.timeout = BatasWaktu;

                yield return request.SendWebRequest();

                if (request.result == UnityWebRequest.Result.Success)
                {
                    string teks = request.downloadHandler.text;
                    KirimDataResponse resp = null;

                    try { resp = JsonUtility.FromJson<KirimDataResponse>(teks); }
                    catch (Exception) { /* ditangani di bawah */ }

                    if (resp != null && resp.success)
                    {
                        Debug.Log("Terkirim: " + label + " | " + teks);
                    }
                    else
                    {
                        // Server menolak datanya: mengirim ulang tidak akan mengubah hasil.
                        Debug.LogError("Server menolak " + label + ": " + teks);
                    }

                    yield break;
                }

                Debug.LogWarning("Gagal kirim " + label + " (percobaan " + percobaan + "/" + MaksPercobaan + "): " + request.error);
            }

            yield return new WaitForSecondsRealtime(JedaCobaUlang);
        }

        Debug.LogError("Menyerah mengirim " + label + " setelah " + MaksPercobaan + " percobaan.");
    }
}
