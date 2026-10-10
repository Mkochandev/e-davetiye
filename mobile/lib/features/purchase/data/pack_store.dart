import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:in_app_purchase/in_app_purchase.dart';
import 'package:in_app_purchase_android/in_app_purchase_android.dart';

/// Etkinlik Paketi ürün kimliği; iki mağazada da aynı, türü consumable (spec §4).
const packProductId = 'event_pack';

class StoreProduct {
  const StoreProduct({required this.id, required this.price});

  final String id;

  /// Mağazanın biçimlendirdiği fiyat (ör. "₺249,99").
  final String price;
}

enum StorePurchaseStatus { pending, purchased, canceled, error }

/// Mağazadan gelen satın alma güncellemesi.
class StorePurchase {
  const StorePurchase({
    required this.productId,
    required this.status,
    this.verificationData = '',
    this.errorMessage,
    this.native,
  });

  final String productId;
  final StorePurchaseStatus status;

  /// iOS: StoreKit 2 işlem JWS'i; Android: satın alma token'ı. Sunucuya `verification_data` olarak gider.
  final String verificationData;
  final String? errorMessage;

  /// Mağaza nesnesi; tamamlama için geri verilir.
  final Object? native;
}

/// Mağaza erişimi (in_app_purchase); testlerde sahtesi kullanılır.
abstract interface class PackStore {
  Stream<List<StorePurchase>> get purchases;

  Future<bool> isAvailable();

  Future<StoreProduct?> loadProduct();

  /// Satın alma penceresini açar; sonuç [purchases] akışından gelir.
  Future<bool> buy(StoreProduct product);

  /// Sunucu onayından sonra: Android'de tüketir (bir sonraki etkinlik için tekrar alınabilsin),
  /// iOS'ta işlemi bitirir. Sunucu reddederse çağrılmaz; Android 3 gün içinde iade eder.
  Future<void> finishVerified(StorePurchase purchase);

  /// İptal/hata gibi teslim edilmeyecek güncellemeleri kapatır.
  Future<void> finishUnverified(StorePurchase purchase);

  /// Tamamlanmamış satın almaları akışa yeniden düşürür (Android). iOS'ta bitmemiş işlemler kendiliğinden gelir.
  Future<void> resendPending();
}

class InAppPurchasePackStore implements PackStore {
  InAppPurchasePackStore([InAppPurchase? iap]) : _iap = iap ?? InAppPurchase.instance;

  final InAppPurchase _iap;
  final Map<String, ProductDetails> _details = {};

  @override
  Stream<List<StorePurchase>> get purchases => _iap.purchaseStream.map((list) => list.map(_map).toList());

  StorePurchase _map(PurchaseDetails p) => StorePurchase(
    productId: p.productID,
    status: switch (p.status) {
      PurchaseStatus.pending => StorePurchaseStatus.pending,
      PurchaseStatus.purchased || PurchaseStatus.restored => StorePurchaseStatus.purchased,
      PurchaseStatus.canceled => StorePurchaseStatus.canceled,
      PurchaseStatus.error => StorePurchaseStatus.error,
    },
    verificationData: p.verificationData.serverVerificationData,
    errorMessage: p.error?.message,
    native: p,
  );

  @override
  Future<bool> isAvailable() => _iap.isAvailable();

  @override
  Future<StoreProduct?> loadProduct() async {
    final response = await _iap.queryProductDetails({packProductId});
    final details = response.productDetails.where((d) => d.id == packProductId).firstOrNull;
    if (details == null) return null;
    _details[details.id] = details;
    return StoreProduct(id: details.id, price: details.price);
  }

  @override
  Future<bool> buy(StoreProduct product) {
    final details = _details[product.id] ?? (throw StateError('Ürün bilgisi yüklenmeden satın alınamaz.'));
    // autoConsume kapalı: Android'de ürün sunucu doğrulamasından önce tüketilmesin (spec §12).
    return _iap.buyConsumable(purchaseParam: PurchaseParam(productDetails: details), autoConsume: false);
  }

  @override
  Future<void> finishVerified(StorePurchase purchase) async {
    final p = purchase.native! as PurchaseDetails;
    if (defaultTargetPlatform == TargetPlatform.android) {
      // Tüketmek onaylamayı da kapsar; ayrıca completePurchase (acknowledge) gerekmez.
      await _iap.getPlatformAddition<InAppPurchaseAndroidPlatformAddition>().consumePurchase(p);
    } else if (p.pendingCompletePurchase) {
      await _iap.completePurchase(p);
    }
  }

  @override
  Future<void> finishUnverified(StorePurchase purchase) async {
    final p = purchase.native! as PurchaseDetails;
    // Android'de iptal/hata için kapatılacak bir şey yok; iOS'ta işlem kuyruğu temizlenir.
    if (defaultTargetPlatform != TargetPlatform.android && p.pendingCompletePurchase) {
      await _iap.completePurchase(p);
    }
  }

  @override
  Future<void> resendPending() async {
    if (defaultTargetPlatform == TargetPlatform.android) {
      await _iap.restorePurchases();
    }
  }
}

final packStoreProvider = Provider<PackStore>((ref) => InAppPurchasePackStore());
