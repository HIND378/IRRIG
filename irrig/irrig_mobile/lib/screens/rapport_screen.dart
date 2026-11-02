import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../services/api_service.dart';
import '../services/sync_service.dart';

/// Détail d'une mission et saisie du rapport (rapport, pièces, photos, signature).
/// L'enregistrement est local : il fonctionne sans aucun réseau.
class RapportScreen extends StatefulWidget {
  const RapportScreen({super.key, required this.mission});
  final Map<String, dynamic> mission;

  @override
  State<RapportScreen> createState() => _RapportScreenState();
}

class _RapportScreenState extends State<RapportScreen> {
  final _sync = SyncService.instance;
  final _formulaire = GlobalKey<FormState>();
  final _rapport = TextEditingController();
  final _signature = TextEditingController();
  final List<_LignePiece> _pieces = [];
  final List<String> _photos = [];
  List<Map<String, dynamic>> _catalogue = [];

  @override
  void initState() {
    super.initState();
    _sync.piecesLocales().then((p) => setState(() => _catalogue = p));
  }

  Future<void> _prendrePhoto() async {
    if (_photos.length >= 5) return;
    final photo = await ImagePicker().pickImage(source: ImageSource.camera, imageQuality: 45, maxWidth: 1280);
    if (photo != null) setState(() => _photos.add(photo.path));
  }

  Future<void> _demarrer() async {
    try {
      await ApiService.instance.demarrer(widget.mission['id'] as int);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Intervention démarrée.')));
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Hors ligne : le démarrage sera visible à la clôture.')));
    }
  }

  Future<void> _enregistrer() async {
    if (!_formulaire.currentState!.validate()) return;
    await _sync.enregistrerRapport(
      interventionId: widget.mission['id'] as int,
      rapport: _rapport.text.trim(),
      pieces: _pieces.where((p) => p.pieceId != null).map((p) => {'piece_id': p.pieceId!, 'quantite': p.quantite}).toList(),
      photos: _photos,
      signatureClient: _signature.text.trim().isEmpty ? null : _signature.text.trim(),
    );
    if (!mounted) return;
    final enLigne = await _sync.enLigne();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(enLigne
          ? 'Rapport enregistré et en cours d\'envoi.'
          : 'Rapport enregistré sur le téléphone. Il sera envoyé automatiquement au retour du réseau.'),
    ));
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final m = widget.mission;
    final eq = m['equipement'];
    final ex = eq['exploitation'];
    final historique = (eq['interventions'] as List?) ?? [];

    return Scaffold(
      appBar: AppBar(title: Text('Intervention n°${m['id']}')),
      body: Form(
        key: _formulaire,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(ex['client']['nom'], style: Theme.of(context).textTheme.titleMedium),
                    Text('${ex['commune']} · Tél. ${ex['client']['telephone']}'),
                    if (ex['latitude'] != null) Text('GPS : ${ex['latitude']}, ${ex['longitude']}'),
                    const Divider(),
                    Text('${eq['type']} — n° ${eq['numero_serie']} ${m['sous_garantie'] == true ? '(sous garantie)' : '(hors garantie)'}'),
                    const SizedBox(height: 6),
                    Text('« ${m['description']} »', style: const TextStyle(fontStyle: FontStyle.italic)),
                    if (historique.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text('Historique : ${historique.map((h) => '${h['type']} ${(h['date_cloture'] ?? '').toString().split('T').first}').join(' · ')}'),
                    ],
                    if (m['statut'] == 'planifiee')
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton.icon(onPressed: _demarrer, icon: const Icon(Icons.play_arrow), label: const Text('Démarrer')),
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _rapport,
              maxLines: 4,
              decoration: const InputDecoration(labelText: 'Travaux réalisés'),
              validator: (v) => (v == null || v.trim().isEmpty) ? 'Décrivez les travaux réalisés' : null,
            ),
            const SizedBox(height: 16),
            Text('Pièces utilisées', style: Theme.of(context).textTheme.titleSmall),
            ..._pieces.asMap().entries.map((e) => Row(
                  key: ObjectKey(e.value),
                  children: [
                    Expanded(
                      flex: 3,
                      child: DropdownButtonFormField<int>(
                        isExpanded: true,
                        value: e.value.pieceId,
                        hint: const Text('Pièce'),
                        items: _catalogue
                            .map((p) => DropdownMenuItem<int>(value: p['id'] as int, child: Text('${p['libelle']}', overflow: TextOverflow.ellipsis)))
                            .toList(),
                        onChanged: (v) => setState(() => e.value.pieceId = v),
                      ),
                    ),
                    const SizedBox(width: 8),
                    SizedBox(
                      width: 70,
                      child: TextFormField(
                        initialValue: '${e.value.quantite}',
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(labelText: 'Qté'),
                        onChanged: (v) => e.value.quantite = int.tryParse(v) ?? 1,
                      ),
                    ),
                    IconButton(onPressed: () => setState(() => _pieces.removeAt(e.key)), icon: const Icon(Icons.delete_outline)),
                  ],
                )),
            TextButton.icon(
              onPressed: _catalogue.isEmpty ? null : () => setState(() => _pieces.add(_LignePiece())),
              icon: const Icon(Icons.add),
              label: Text(_catalogue.isEmpty ? 'Catalogue non téléchargé' : 'Ajouter une pièce'),
            ),
            const SizedBox(height: 8),
            Text('Photos (${_photos.length}/5)', style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 6),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                ..._photos.map((p) => ClipRRect(borderRadius: BorderRadius.circular(8), child: Image.file(File(p), width: 80, height: 80, fit: BoxFit.cover))),
                if (_photos.length < 5)
                  OutlinedButton.icon(onPressed: _prendrePhoto, icon: const Icon(Icons.photo_camera), label: const Text('Photo')),
              ],
            ),
            const SizedBox(height: 16),
            TextFormField(controller: _signature, decoration: const InputDecoration(labelText: 'Nom du client (signature)')),
            const SizedBox(height: 20),
            FilledButton.icon(
              onPressed: _enregistrer,
              icon: const Icon(Icons.save),
              label: const Text('Clôturer et enregistrer'),
              style: FilledButton.styleFrom(padding: const EdgeInsets.all(16)),
            ),
          ],
        ),
      ),
    );
  }
}

class _LignePiece {
  int? pieceId;
  int quantite = 1;
}
