using UnityEngine;
using UnityEngine.UI;

public class MusicToggle : MonoBehaviour
{
    AudioSource musicSource;

    public Image musicIcon;        // opsional
    public Sprite musicOnSprite;   // opsional
    public Sprite musicOffSprite;  // opsional

    void Start()
    {
        GameObject obj = GameObject.FindGameObjectWithTag("BGM");
        if (obj != null)
            musicSource = obj.GetComponent<AudioSource>();
    }

    public void ToggleMusic()
    {
        if (musicSource == null) return;

        if (musicSource.isPlaying)
            musicSource.Pause();
        else
            musicSource.Play();

        if (musicIcon != null)
            musicIcon.sprite = musicSource.isPlaying ? musicOnSprite : musicOffSprite;
    }
}