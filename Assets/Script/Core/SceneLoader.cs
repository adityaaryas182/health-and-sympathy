using UnityEngine;
using UnityEngine.SceneManagement;

public class SceneLoader : MonoBehaviour
{
    public void LoadScene(string sceneName)
    {
        Time.timeScale = 1f; // pastikan game tidak beku di scene berikutnya
        SceneManager.LoadScene(sceneName);
    }
}