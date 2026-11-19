using UnityEngine;

[ExecuteAlways]
[RequireComponent(typeof(RectTransform))]
public sealed class ResponsiveArtboard : MonoBehaviour
{
    [SerializeField]
    private Vector2 referenceSize = new Vector2(910f, 507f);

    private RectTransform artboard;
    private RectTransform parentRect;

    private void OnEnable()
    {
        CacheComponents();
        FitToParent();
    }

    private void LateUpdate()
    {
        FitToParent();
    }

    private void CacheComponents()
    {
        artboard = GetComponent<RectTransform>();
        parentRect = transform.parent as RectTransform;
    }

    private void FitToParent()
    {
        if (artboard == null || parentRect == null)
        {
            CacheComponents();
        }

        if (artboard == null || parentRect == null)
        {
            return;
        }

        float parentWidth = parentRect.rect.width;
        float parentHeight = parentRect.rect.height;

        if (parentWidth <= 0f || parentHeight <= 0f)
        {
            return;
        }

        float widthScale = parentWidth / referenceSize.x;
        float heightScale = parentHeight / referenceSize.y;
        float finalScale = Mathf.Min(widthScale, heightScale);

        artboard.anchorMin = new Vector2(0.5f, 0.5f);
        artboard.anchorMax = new Vector2(0.5f, 0.5f);
        artboard.pivot = new Vector2(0.5f, 0.5f);
        artboard.sizeDelta = referenceSize;
        artboard.anchoredPosition = Vector2.zero;
        artboard.localScale =
            new Vector3(finalScale, finalScale, 1f);
    }
}