using UnityEngine;

[ExecuteAlways]
[RequireComponent(typeof(RectTransform))]
public sealed class SafeAreaController : MonoBehaviour
{
    private RectTransform panel;
    private Rect previousSafeArea;
    private Vector2Int previousScreenSize;

    private void OnEnable()
    {
        panel = GetComponent<RectTransform>();
        ApplySafeArea();
    }

    private void Update()
    {
        Vector2Int currentScreenSize =
            new Vector2Int(Screen.width, Screen.height);

        bool resolutionChanged =
            currentScreenSize != previousScreenSize;

        bool safeAreaChanged =
            Screen.safeArea != previousSafeArea;

        if (resolutionChanged || safeAreaChanged)
        {
            ApplySafeArea();
        }
    }

    private void ApplySafeArea()
    {
        if (panel == null || Screen.width <= 0 || Screen.height <= 0)
        {
            return;
        }

        Rect safeArea = Screen.safeArea;

        Vector2 anchorMin = safeArea.position;
        Vector2 anchorMax = safeArea.position + safeArea.size;

        anchorMin.x /= Screen.width;
        anchorMin.y /= Screen.height;

        anchorMax.x /= Screen.width;
        anchorMax.y /= Screen.height;

        panel.anchorMin = anchorMin;
        panel.anchorMax = anchorMax;
        panel.offsetMin = Vector2.zero;
        panel.offsetMax = Vector2.zero;

        previousSafeArea = safeArea;
        previousScreenSize =
            new Vector2Int(Screen.width, Screen.height);
    }
}