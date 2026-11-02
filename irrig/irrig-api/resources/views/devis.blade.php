<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Devis {{ $devis->numero }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2a24; margin: 40px; }
        header { display: flex; justify-content: space-between; border-bottom: 3px solid #2e7d4f; padding-bottom: 12px; }
        h1 { color: #2e7d4f; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border: 1px solid #c9d3cc; padding: 8px; text-align: left; }
        th { background: #eef4f0; }
        .num { text-align: right; }
        .totaux td { font-weight: bold; }
        footer { margin-top: 40px; font-size: 12px; color: #5b6b61; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<button class="no-print" onclick="window.print()">Imprimer / Enregistrer en PDF</button>
<header>
    <div>
        <h1>IRRIG</h1>
        <div>Agence de {{ $devis->client->agence->ville }}</div>
    </div>
    <div>
        <strong>Devis {{ $devis->numero }}</strong><br>
        Date : {{ $devis->date->format('d/m/Y') }}<br>
        Commercial : {{ $devis->commercial->name }}
    </div>
</header>

<p><strong>Client :</strong> {{ $devis->client->nom }} — {{ $devis->client->telephone }}</p>

<table>
    <thead>
    <tr><th>Désignation</th><th class="num">Quantité</th><th class="num">Prix unitaire (DH)</th><th class="num">Total (DH)</th></tr>
    </thead>
    <tbody>
    @foreach ($devis->lignes as $l)
        <tr>
            <td>{{ $l['designation'] }}</td>
            <td class="num">{{ $l['quantite'] }}</td>
            <td class="num">{{ number_format($l['prix_unitaire'], 2, ',', ' ') }}</td>
            <td class="num">{{ number_format($l['quantite'] * $l['prix_unitaire'], 2, ',', ' ') }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot class="totaux">
    <tr><td colspan="3">Total HT</td><td class="num">{{ number_format($devis->montant_ht, 2, ',', ' ') }}</td></tr>
    <tr><td colspan="3">TVA 20 %</td><td class="num">{{ number_format($devis->montant_ttc - $devis->montant_ht, 2, ',', ' ') }}</td></tr>
    <tr><td colspan="3">Total TTC</td><td class="num">{{ number_format($devis->montant_ttc, 2, ',', ' ') }}</td></tr>
    </tfoot>
</table>

<footer>Devis valable 30 jours. IRRIG — solutions d'irrigation pour les zones rurales.</footer>
</body>
</html>
