using UnityEngine;
using UnityEngine.UI;
using UnityEngine.Video;
using TMPro;

public class VideoPlayerController : MonoBehaviour
{
    public VideoPlayer videoPlayer;
    public Image playPauseIcon;
    public Sprite playSprite;
    public Sprite pauseSprite;
    public Slider seekBar;

    public TMP_Text txtCurrent;   // TMP
    public TMP_Text txtTotal;     // TMP

    bool isScrubbing = false;

    void Update()
    {
        if (!isScrubbing && videoPlayer.frameCount > 0)
        {
            float progress = (float)(videoPlayer.frame / (double)videoPlayer.frameCount);
            seekBar.SetValueWithoutNotify(progress);
        }

        playPauseIcon.sprite = videoPlayer.isPlaying ? pauseSprite : playSprite;

        if (videoPlayer.frameCount > 0 && videoPlayer.frameRate > 0)
        {
            double current = videoPlayer.frame / videoPlayer.frameRate;
            double total = videoPlayer.frameCount / videoPlayer.frameRate;
            txtCurrent.text = FormatTime(current);
            txtTotal.text = FormatTime(total);
        }
    }

    string FormatTime(double seconds)
    {
        int m = (int)(seconds / 60);
        int s = (int)(seconds % 60);
        return string.Format("{0:00}:{1:00}", m, s);
    }

    public void TogglePlayPause()
    {
        if (videoPlayer.isPlaying)
            videoPlayer.Pause();
        else
            videoPlayer.Play();
    }

    public void OnScrubStart() { isScrubbing = true; }

    public void OnScrubEnd()
    {
        if (videoPlayer.frameCount > 0)
            videoPlayer.frame = (long)(seekBar.value * videoPlayer.frameCount);
        isScrubbing = false;
    }
}