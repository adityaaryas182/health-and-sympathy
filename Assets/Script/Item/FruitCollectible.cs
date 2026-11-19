using UnityEngine;

// Dipasang pada Strawberry di Level 01-03.
public class FruitCollectible : MonoBehaviour
{
    [Header("Nilai buah")]
    [SerializeField] private int fruitValue = 1;

    private bool alreadyCollected = false;

    private void OnTriggerEnter2D(Collider2D other)
    {
        // Mencegah buah dikumpulkan dua kali.
        if (alreadyCollected)
            return;

        if (!other.CompareTag("Player"))
            return;

        PlayerHealth playerHealth = other.GetComponent<PlayerHealth>();

        if (playerHealth == null)
            return;

        // Buah selalu bisa diambil (juga saat nyawa penuh); lihat PlayerHealth.CollectFruit.
        if (playerHealth.CollectFruit(fruitValue))
        {
            alreadyCollected = true;
            Destroy(gameObject);
        }
    }
}