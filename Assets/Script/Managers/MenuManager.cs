using UnityEngine;
using UnityEngine.Video;

public class MenuManager : MonoBehaviour
{
    [Header("Panels")]
    public GameObject mainMenuPanel;     // panel menu utama (3 kartu)
    public GameObject videoPanel;        // panel pilih video (SYMPATHY, HEALTH)
    public GameObject videoPlayerPanel;  // panel pemutar video

    [Header("Video")]
    public VideoPlayer videoPlayer;      // komponen VideoPlayer (di BgVideo)
    public VideoClip videoSympathy;
    public VideoClip videoHealth;

    void Start()
    {
        ShowOnly(mainMenuPanel); // pastikan mulai dari menu utama
    }

    // helper: hanya satu panel yang hidup
    void ShowOnly(GameObject panel)
    {
        mainMenuPanel.SetActive(panel == mainMenuPanel);
        videoPanel.SetActive(panel == videoPanel);
        videoPlayerPanel.SetActive(panel == videoPlayerPanel);
    }

    // tombol VIDEO LESSONS
    public void OpenVideoPanel() => ShowOnly(videoPanel);

    // tombol back di VideoPanel
    public void BackToMainMenu() => ShowOnly(mainMenuPanel);

    // tombol back di VideoPlayerPanel
    public void BackToVideoPanel()
    {
        videoPlayer.Stop();
        ShowOnly(videoPanel);
    }

    // BtnVid1 panggil PlayVideo(0), BtnVid2 panggil PlayVideo(1)
    public void PlayVideo(int index)
    {
        ShowOnly(videoPlayerPanel);
        videoPlayer.clip = (index == 0) ? videoSympathy : videoHealth;
        videoPlayer.Play();
    }
}