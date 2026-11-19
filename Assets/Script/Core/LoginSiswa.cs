using System;
using System.Collections;
using System.Collections.Generic;
using TMPro;
using UnityEngine;
using UnityEngine.Networking;
using UnityEngine.SceneManagement;
using UnityEngine.UI;

public class LoginSiswa : MonoBehaviour
{
    [Header("Komponen Login")]
    [SerializeField] private TMP_InputField usernameInput;
    [SerializeField] private TMP_InputField nisInput;
    [SerializeField] private Button loginButton;
    [SerializeField] private TMP_Text statusText;

    [Header("Konfigurasi Server")]
    [SerializeField]
    private string loginUrl =
        "http://localhost/backend/api/login_siswa.php";

    [Header("Scene Setelah Login")]
    [SerializeField] private string nextSceneName = "MainMenu";

    private bool isLoggingIn;

    [Serializable]
    public class SiswaData
    {
        public int id_siswa;
        public string nis;
        public string nama_siswa;
        public string username;
        public string kelas;
    }

    [Serializable]
    public class LoginResponse
    {
        public bool success;
        public string message;
        public SiswaData data;
    }

    public void Login()
    {
        if (isLoggingIn)
        {
            return;
        }

        string username = usernameInput.text.Trim();
        string nis = nisInput.text.Trim();

        if (string.IsNullOrEmpty(username))
        {
            SetStatus("Username belum diisi.");
            usernameInput.ActivateInputField();
            return;
        }

        if (string.IsNullOrEmpty(nis))
        {
            SetStatus("NIS belum diisi.");
            nisInput.ActivateInputField();
            return;
        }

        StartCoroutine(LoginCoroutine(username, nis));
    }

    private IEnumerator LoginCoroutine(string username, string nis)
    {
        isLoggingIn = true;

        if (loginButton != null)
        {
            loginButton.interactable = false;
        }

        SetStatus("Sedang memeriksa akun...");

        var formData = new Dictionary<string, string>
        {
            { "username", username },
            { "nis", nis }
        };

        using UnityWebRequest request =
            UnityWebRequest.Post(loginUrl, formData);

        request.timeout = 15;

        yield return request.SendWebRequest();

        string responseText =
            request.downloadHandler != null
                ? request.downloadHandler.text
                : string.Empty;

        Debug.Log("Respons login: " + responseText);

        if (request.result != UnityWebRequest.Result.Success)
        {
            SetStatus(
                "Tidak dapat terhubung ke server.\n" +
                request.error
            );

            Debug.LogError(
                "Login request gagal: " +
                request.error +
                "\nRespons server: " +
                responseText
            );

            FinishRequest();
            yield break;
        }

        if (string.IsNullOrWhiteSpace(responseText))
        {
            SetStatus("Server tidak memberikan respons.");
            FinishRequest();
            yield break;
        }

        LoginResponse response;

        try
        {
            response =
                JsonUtility.FromJson<LoginResponse>(responseText);
        }
        catch (Exception exception)
        {
            SetStatus("Format respons server tidak valid.");

            Debug.LogError(
                "Gagal membaca JSON: " +
                exception.Message +
                "\nRespons: " +
                responseText
            );

            FinishRequest();
            yield break;
        }

        if (response == null)
        {
            SetStatus("Respons login tidak dapat dibaca.");
            FinishRequest();
            yield break;
        }

        if (!response.success || response.data == null)
        {
            SetStatus(response.message);
            FinishRequest();
            yield break;
        }

        // Simpan identitas siswa untuk scene berikutnya
        PlayerPrefs.SetInt(
            "id_siswa",
            response.data.id_siswa
        );

        PlayerPrefs.SetString(
            "nama_siswa",
            response.data.nama_siswa
        );

        PlayerPrefs.SetString(
            "username_siswa",
            response.data.username
        );

        PlayerPrefs.SetString(
            "kelas_siswa",
            response.data.kelas
        );

        PlayerPrefs.Save();

        // Siswa baru login: skor siswa sebelumnya di perangkat ini tidak boleh terbawa.
        ScoreManager.HapusSkorTersimpan();

        SetStatus(
            "Login berhasil.\nSelamat datang, " +
            response.data.nama_siswa
        );

        yield return new WaitForSeconds(0.7f);

        SceneManager.LoadScene(nextSceneName);
    }

    private void FinishRequest()
    {
        isLoggingIn = false;

        if (loginButton != null)
        {
            loginButton.interactable = true;
        }
    }

    private void SetStatus(string message)
    {
        if (statusText != null)
        {
            statusText.text = message;
        }

        Debug.Log(message);
    }
}