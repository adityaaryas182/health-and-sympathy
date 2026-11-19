using UnityEngine;

[RequireComponent(typeof(Rigidbody2D))]
public class PlayerMovement : MonoBehaviour
{
    [Header("Movement Settings")]
    [SerializeField] private float moveSpeed = 5f;

    private Rigidbody2D rb;
    private Animator anim;
    private SpriteRenderer spriteRenderer;

    private float moveInput;

    private void Awake()
    {
        rb = GetComponent<Rigidbody2D>();
        anim = GetComponent<Animator>();
        spriteRenderer = GetComponent<SpriteRenderer>();
    }

    private void Update()
    {
        // Ambil input kiri-kanan
        moveInput = Input.GetAxisRaw("Horizontal");

        // Animasi idle/run
        if (anim != null)
        {
            anim.SetFloat("Speed", Mathf.Abs(moveInput));
        }

        // Balik arah sprite
        if (moveInput > 0)
        {
            spriteRenderer.flipX = false;
        }
        else if (moveInput < 0)
        {
            spriteRenderer.flipX = true;
        }
    }

    private void FixedUpdate()
    {
        // Gerakkan player lewat physics
        rb.linearVelocity = new Vector2(moveInput * moveSpeed, rb.linearVelocity.y);
    }
}