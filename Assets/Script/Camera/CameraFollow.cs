using UnityEngine;

[DefaultExecutionOrder(-100)]
[RequireComponent(typeof(Camera))]
public class CameraFollow : MonoBehaviour
{
    [SerializeField] private Transform target;
    [SerializeField] private float smoothSpeed = 5f;
    [SerializeField] private Vector3 offset = new Vector3(2f, 0f, -10f);
    [SerializeField] private bool followY = false;
    [SerializeField] private Transform leftLimit;
    [SerializeField] private Transform rightLimit;

    private Camera cam;

    private void Awake()
    {
        cam = GetComponent<Camera>();
    }

    private void Start()
    {
        SnapNow();
    }

    private void LateUpdate()
    {
        FollowTarget(true);
    }

    public void SnapNow()
    {
        FollowTarget(false);
    }

    private void FollowTarget(bool smooth)
    {
        if (target == null || cam == null) return;

        float desiredX = target.position.x + offset.x;
        float desiredY = followY ? target.position.y + offset.y : transform.position.y;

        float halfWidth = cam.orthographicSize * cam.aspect;

        if (leftLimit != null && rightLimit != null)
        {
            desiredX = Mathf.Clamp(
                desiredX,
                leftLimit.position.x + halfWidth,
                rightLimit.position.x - halfWidth
            );
        }

        Vector3 desiredPosition = new Vector3(desiredX, desiredY, -10f);

        if (smooth)
        {
            transform.position = Vector3.Lerp(
                transform.position,
                desiredPosition,
                smoothSpeed * Time.deltaTime
            );
        }
        else
        {
            transform.position = desiredPosition;
        }
    }
}