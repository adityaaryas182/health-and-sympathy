using UnityEngine;
using UnityEngine.SceneManagement;

public class MusicManager : MonoBehaviour
{
    public static MusicManager instance;

    public AudioClip menuMusic;   // untuk semua scene menu
    public AudioClip gameMusic;   // untuk Level 01, 02, 03

    // daftar scene yang pakai musik game
    public string[] gameScenes = { "Level 01", "Level 02", "Level 03" };

    private AudioSource audioSource;

    void Awake()
    {
        // pastikan cuma ada satu MusicManager
        if (instance != null)
        {
            Destroy(gameObject);
            return;
        }
        instance = this;
        DontDestroyOnLoad(gameObject);

        audioSource = GetComponent<AudioSource>();
        audioSource.loop = true;

        SceneManager.sceneLoaded += OnSceneLoaded;
    }

    void Start()
    {
        // putar musik untuk scene pertama saat game dimulai
        PlayMusicFor(SceneManager.GetActiveScene().name);
    }

    void OnSceneLoaded(Scene scene, LoadSceneMode mode)
    {
        PlayMusicFor(scene.name);
    }

    void PlayMusicFor(string sceneName)
    {
        AudioClip targetClip = IsGameScene(sceneName) ? gameMusic : menuMusic;

        // kalau lagu yang benar sudah diputar, jangan restart
        if (audioSource.clip == targetClip && audioSource.isPlaying)
            return;

        audioSource.clip = targetClip;
        audioSource.Play();
    }

    bool IsGameScene(string sceneName)
    {
        foreach (string s in gameScenes)
        {
            if (s == sceneName) return true;
        }
        return false;
    }

    void OnDestroy()
    {
        SceneManager.sceneLoaded -= OnSceneLoaded;
    }
}