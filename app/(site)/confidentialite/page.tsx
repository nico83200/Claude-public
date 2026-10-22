export const metadata = { title: "Confidentialité" };

export default function Confidentialite() {
  return (
    <div className="container" style={{ maxWidth: 760, paddingTop: 40 }}>
      <h1>Confidentialité et données personnelles</h1>
      <p className="muted">Dernière mise à jour : à compléter avant la mise en ligne (modèle à faire valider par un juriste).</p>
      <h2>Porteurs de carte (clients)</h2>
      <p>Nous traitons les informations de votre profil, de vos commandes et de votre compte pour fournir le service. Vous choisissez chaque information rendue publique sur votre profil et pouvez la modifier ou la supprimer à tout moment. Les données sont hébergées dans l’Union européenne.</p>
      <h2>Personnes qui scannent une carte</h2>
      <p>Lorsqu’une carte est scannée, nous enregistrons de façon agrégée la date, le pays approximatif et le type d’appareil, afin de fournir des statistiques au porteur. <strong>Aucune adresse IP n’est conservée et aucun cookie de suivi n’est déposé.</strong></p>
      <p>Si vous utilisez le formulaire « Échanger mes coordonnées », les informations saisies sont transmises uniquement au porteur de la carte, avec votre consentement explicite.</p>
      <h2>Vos droits</h2>
      <p>Vous disposez d’un droit d’accès, de rectification, d’effacement et d’opposition. Contact : <a href="mailto:dpo@tapikard.com">dpo@tapikard.com</a>. Vous pouvez également saisir la CNIL.</p>
    </div>
  );
}
