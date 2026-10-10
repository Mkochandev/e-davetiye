import 'dart:async';

import 'package:zarf/features/purchase/data/pack_store.dart';

/// Mağazanın yerine geçer: ürün/fiyat, satın alma akışı ve tamamlama çağrıları kaydedilir.
class FakePackStore implements PackStore {
  FakePackStore({this.available = true, this.price = '₺249,99'});

  bool available;
  String price;
  final controller = StreamController<List<StorePurchase>>.broadcast();
  final bought = <String>[];
  final finished = <StorePurchase>[];
  final unfinished = <StorePurchase>[];
  int resendCalls = 0;

  /// `buy` çağrılınca akışa düşecek güncellemeler; boşsa test kendisi gönderir.
  List<StorePurchase> Function()? onBuy;

  void emit(StorePurchase p) => controller.add([p]);

  static StorePurchase purchased([String data = 'store-token']) =>
      StorePurchase(productId: packProductId, status: StorePurchaseStatus.purchased, verificationData: data);

  @override
  Stream<List<StorePurchase>> get purchases => controller.stream;

  @override
  Future<bool> isAvailable() async => available;

  @override
  Future<StoreProduct?> loadProduct() async => StoreProduct(id: packProductId, price: price);

  @override
  Future<bool> buy(StoreProduct product) async {
    bought.add(product.id);
    final updates = onBuy?.call();
    if (updates != null) scheduleMicrotask(() => controller.add(updates));
    return true;
  }

  @override
  Future<void> finishVerified(StorePurchase purchase) async => finished.add(purchase);

  @override
  Future<void> finishUnverified(StorePurchase purchase) async => unfinished.add(purchase);

  @override
  Future<void> resendPending() async => resendCalls++;
}
