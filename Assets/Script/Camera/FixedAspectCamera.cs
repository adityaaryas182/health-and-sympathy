using UnityEngine;

[DefaultExecutionOrder(-200)]
[RequireComponent(typeof(Camera))]
public class FixedAspectCamera : MonoBehaviour
{
    [SerializeField] private float targetAspect = 16f / 9f;

    private Camera cam;
    private int lastWidth;
    private int lastHeight;

    private void Awake()
    {
        cam = GetComponent<Camera>();
        ApplyAspect();
    }

    private void Start()
    {
        ApplyAspect();
    }

    private void LateUpdate()
    {
        if (Screen.width != lastWidth || Screen.height != lastHeight)
        {
            ApplyAspect();
        }
    }

    private void ApplyAspect()
    {
        lastWidth = Screen.width;
        lastHeight = Screen.height;

        float windowAspect = (float)Screen.width / Screen.height;
        float scaleHeight = windowAspect / targetAspect;

        if (scaleHeight < 1f)
        {
            cam.rect = new Rect(0f, (1f - scaleHeight) / 2f, 1f, scaleHeight);
        }
        else
        {
            float scaleWidth = 1f / scaleHeight;
            cam.rect = new Rect((1f - scaleWidth) / 2f, 0f, scaleWidth, 1f);
        }
    }
}