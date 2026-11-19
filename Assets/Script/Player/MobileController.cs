using UnityEngine;

public class MobileController : MonoBehaviour
{
    [Header("Pengaturan Karakter")]
    public float kecepatan = 5f;
    public float kekuatanLompat = 7f;

    private Rigidbody2D rb;
    private bool gerakKiri, gerakKanan;

    void Start()
    {
        // Mengambil komponen Rigidbody2D pada karakter
        rb = GetComponent<Rigidbody2D>();
    }

    void Update()
    {
        // Eksekusi pergerakan berdasarkan tombol yang ditahan
        if (gerakKiri)
        {
            rb.linearVelocity = new Vector2(-kecepatan, rb.linearVelocity.y);
        }
        else if (gerakKanan)
        {
            rb.linearVelocity = new Vector2(kecepatan, rb.linearVelocity.y);
        }
        else
        {
            // Jika tombol dilepas, karakter berhenti
            rb.linearVelocity = new Vector2(0, rb.linearVelocity.y);
        }
    }

    // --- FUNGSI UNTUK TOMBOL KIRI ---
    public void TekanKiri() { gerakKiri = true; }
    public void LepasKiri() { gerakKiri = false; }

    // --- FUNGSI UNTUK TOMBOL KANAN ---
    public void TekanKanan() { gerakKanan = true; }
    public void LepasKanan() { gerakKanan = false; }

    // --- FUNGSI UNTUK LOMPAT (TOMBOL ATAS) ---
    public void Lompat()
    {
        rb.linearVelocity = new Vector2(rb.linearVelocity.x, kekuatanLompat);
    }
    
    // --- FUNGSI UNTUK TOMBOL BAWAH ---
    public void TekanBawah() 
    { 
        // Bisa diisi untuk animasi merunduk atau turun tangga nanti
        Debug.Log("Tombol Bawah ditekan!"); 
    }
    public void ResetGerakan()
    {
        gerakKiri = false;
        gerakKanan = false;

        if (rb != null)
        {
            rb.linearVelocity = new Vector2(0f, rb.linearVelocity.y);
        }
    }
}