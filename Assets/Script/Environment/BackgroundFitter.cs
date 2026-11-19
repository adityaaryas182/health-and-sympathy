using UnityEngine;

[DefaultExecutionOrder(1000)]
[RequireComponent(typeof(SpriteRenderer))]
public class BackgroundFitter : MonoBehaviour
{
    [Header("Camera")]
    [SerializeField] private Camera targetCamera;

    [Header("Tambahan ukuran agar tidak ada celah")]
    [SerializeField] private float extraWidth = 2f;
    [SerializeField] private float extraHeight = 2f;

    private SpriteRenderer sr;
    private float backgroundZ;

    private void Awake()
    {
        sr = GetComponent<SpriteRenderer>();

        if (targetCamera == null)
        {
            targetCamera = Camera.main;
        }

        backgroundZ = transform.position.z;

        // Wajib agar sr.size dapat memperbesar gambar background.
        sr.drawMode = SpriteDrawMode.Tiled;
    }

    private void LateUpdate()
    {
        FitToCameraViewport();
    }

    private void FitToCameraViewport()
    {
        if (targetCamera == null || sr == null)
            return;

        if (!targetCamera.orthographic)
            return;

        // Jarak bidang background dari kamera.
        float distanceFromCamera = Mathf.Abs(
            backgroundZ - targetCamera.transform.position.z
        );

        // Ambil titik sudut viewport kamera di dunia game.
        Vector3 bottomLeft = targetCamera.ViewportToWorldPoint(
            new Vector3(0f, 0f, distanceFromCamera)
        );

        Vector3 topRight = targetCamera.ViewportToWorldPoint(
            new Vector3(1f, 1f, distanceFromCamera)
        );

        // Pusatkan background tepat mengikuti area yang dirender kamera.
        Vector3 center = (bottomLeft + topRight) * 0.5f;

        transform.position = new Vector3(
            center.x,
            center.y,
            backgroundZ
        );

        // Lebarkan background sedikit supaya tidak muncul garis/celah.
        sr.size = new Vector2(
            (topRight.x - bottomLeft.x) + extraWidth,
            (topRight.y - bottomLeft.y) + extraHeight
        );
    }
}