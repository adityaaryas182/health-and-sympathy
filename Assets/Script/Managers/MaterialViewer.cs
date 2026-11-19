using UnityEngine;
using UnityEngine.UI;

public class MaterialViewer : MonoBehaviour
{
    [System.Serializable]
    public class Lesson
    {
        public string lessonName;
        public Sprite[] pages;
    }

    public Lesson[] lessons;
    public GameObject lessonPanel;   // BARU: panel pemilihan lesson (BookPanel)
    public GameObject materialPanel;
    public Image displayImage;
    public Button nextButton;
    public Button backButton;

    private Sprite[] currentPages;
    private int index = 0;

    void Start()
    {
        nextButton.onClick.AddListener(Next);
        backButton.onClick.AddListener(Back);

        lessonPanel.SetActive(true);    // pastikan pilihan lesson tampil di awal
        materialPanel.SetActive(false);
    }

    public void OpenLesson(int lessonIndex)
    {
        currentPages = lessons[lessonIndex].pages;
        index = 0;

        lessonPanel.SetActive(false);   // BARU: sembunyikan seluruh layar lessons
        materialPanel.SetActive(true);
        ShowPage();
    }

    public void CloseLesson()
    {
        materialPanel.SetActive(false);
        lessonPanel.SetActive(true);    // BARU: tampilkan lagi pilihan lesson
    }

    void Next()
    {
        if (index < currentPages.Length - 1) { index++; ShowPage(); }
    }

    void Back()
    {
        if (index > 0) { index--; ShowPage(); }
    }

    void ShowPage()
    {
        displayImage.sprite = currentPages[index];
        backButton.interactable = index > 0;
        nextButton.interactable = index < currentPages.Length - 1;
    }
}