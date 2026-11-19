using UnityEngine;

public class FruitPickup : MonoBehaviour
{
    [Header("Nilai buah")]
    [SerializeField] private int fruitValue = 1;

    private bool alreadyCollected = false;

    private void OnTriggerEnter2D(Collider2D other)
    {
        // Mencegah buah dikumpulkan dua kali.
        if (alreadyCollected)
            return;

        // Cari script PlayerHealth pada object Player
        // yang menyentuh buah.
        PlayerHealth playerHealth = other.GetComponent<PlayerHealth>();

        if (playerHealth == null)
            return;

        // Buah hanya hilang jika berhasil dimakan.
        bool berhasilDimakan = playerHealth.CollectFruit(fruitValue);

        if (berhasilDimakan)
        {
            alreadyCollected = true;

            Debug.Log("Buah berhasil dimakan.");

            Destroy(gameObject);
        }
    }
}