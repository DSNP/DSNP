# DSNP: Distributed Social Networking Protocol
private. decentralized. scalable.

*** NOTICE *** DSNP is no longer active.

## What is DSNP?
DSNP is a protocol for distributed social networking. It allows everyone to collaborate to create one social network that is decentralized, like the Internet itself. It is an open technology that supports private communications, in a manner that users of modern social networks have come to expect. DSNP aims to cover any use case that can be described as first creating a profile for yourself, establishing connections to people you know, then broadcasting private information to those people.

## Why DSNP?
It is well known that the value of any network is determined by its membership. It is therefore natural for a single social network to emerge as the most popular. Unfortunately, all social networks are currently married to the entity that hosts them, and so the most popular social network will inevitably be contained within a single proprietary database. As it grows, a disturbing reality emerges: we are doomed to create a massive database that contains all of our personal information. Worse still, we will have freely built it for the owner to exploit as it wishes. This doesn't make much sense in the long term. Let us free ourselves from this bind as soon as we are able to.

The central idea behind DSNP is that users should be free to choose where they host their profile, without the decision impacting who they are able to invite as connections. DSNP introduces competition among providers, without imposing segregation of users. By separating the social network from the provider, we can grow the social network without an unsettling dependence on any single provider.

## Security
It is easy to share information on the web. It is harder to share it only with those you choose. DSNP leverages RSA public key cryptography for identity, authentication, the sharing of secrets and the declaration of facts. It can be described as a public-key cryptosystem for web-based identities. It relies on SSL to bootstrap security (distribute public keys) on the grounds that SSL is already required to secure the web interface.
Overview Paper
This short paper (5 pages) provides a quick overview of DSNP.
The Protocol and Reference Implementation
Although the basic techniques are established, DSNP changes rapidly. Each new protocol version is incompatible with the previous. For the time being, it is recommended that only sites controlled by the same administrator peer with each other.
The reference implementation comes in two parts. The DSNPd daemon handles identity, authentication, encryption, verification, and message distribution. This component is reusable by other projects that wish to communicate over DSNP. The content manager is called Choice Social. It is a PHP application that the user accesses in the usual browser-based way to manage and view content. It communicates with the DSNP daemon using a socket.

A release-early, release-often approach will be taken. Please try this software out, even locally on your own machine, and discuss on the mailing list.

### protocol
DSNP Version:	0.6

Document Revision:	11

Date:	May 19, 2011

Document:	[dsnp-spec.pdf](dsnp-spec.pdf)

All Docs:	[spec/](https://web.archive.org/web/20121219191653/http://www.complang.org/dsnp/spec/)

### daemon
Software Version:	0.13

Date:	May 17, 2011

Dist:	[dsnpd-0.13.tar.gz](https://web.archive.org/web/20140615040259/http://www.complang.org:80/dsnp/dsnpd-0.13.tar.gz)

### content manager
Software Version:	0.13

Date:	May 17, 2011

Dist:	[choicesocial-0.13.tar.gz](https://web.archive.org/web/20140615040259/http://www.complang.org:80/dsnp/choicesocial-0.13.tar.gz)

To test a private instance of DSNPd/ChoiceSocial all you need is a Linux box. You will need to generate your own certs and add them to a custom CA-CERT file. You can set up a network of instances this way if you like.

To run a public instance you will need:

A domain with an SSL cert
Root access on the host
A Linode and an SSL cert from Namecheap will do. You don't need to put DSNP at the root of the domain, so an existing site for which you have a cert will do.
Mailing List and Source Code Repository
Mailing list archives: dsnp-interest.
The source code repository for the specification and reference implementation:

  git://git.complang.org/dsnp.git (dead)

## History
DSNP started in late 2007. My original idea was called "Friends in Feed," and it was to adapt PGP to web-based identities [1]. However, a few probelms emerged. The biggest was the cost of encrypting messages to a large number of contacts. As the frequency of messages go up to many per hour, as is often the case in microblogging, the cost of thousands of users on a single site encrypting messages to thousands of friends becomes prohibitive. To solve this problem, DSNP uses a pre-shared broadcast key, a notion that PGP doesn't have.
PGP also reveals senders and recipients. While this is normal and expected for PGP users, in social networking it reveals more than we need to because most communication takes place over pre-established channels. There is no sense in revealing sender and recipient on every single status update. Instead, we securely establish identifiers at friend-request time and use that to label the sender/recipient pair on encrypted messages.

DSNP uses a number of key pairs per identity. Key pairs are also allocated per relationship, and per broadcast group. Doing this allows us to deny signatures by revealing private keys. While the RSA algorithm gives us strong assurances about who published what, we must be careful to protect ourselves against these proofs being used against us.

http://lists.gnupg.org/pipermail/gnupg-users/2007-December/032268.html.

Author

Adrian Thurston is responsible for this.
